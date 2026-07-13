<?php

namespace App\Services;

use App\Models\Appointment;
use App\Models\Transaction;
use App\Models\TransactionAdjustment;
use Illuminate\Database\QueryException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PaymentService
{
    public function __construct(
        private readonly TransactionNumber $transactionNumbers,
    ) {}

    /**
     * Create a standalone charge, or reuse the single charge linked to an appointment.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function createOrApply(array $attributes, int $actorId): Transaction
    {
        $idempotencyKey = trim((string) ($attributes['idempotency_key'] ?? ''));
        $idempotencyKey = $idempotencyKey === '' ? null : $idempotencyKey;
        $allowedReplayActions = ! empty($attributes['appointment_id'])
            ? [TransactionAdjustment::ACTION_CREATED, TransactionAdjustment::ACTION_PAYMENT]
            : [TransactionAdjustment::ACTION_CREATED];

        try {
            return DB::transaction(function () use ($attributes, $actorId, $idempotencyKey, $allowedReplayActions): Transaction {
                if ($replayed = $this->replayed($idempotencyKey, allowedActions: $allowedReplayActions)) {
                    $this->assertCreationReplayMatches($replayed, $attributes);

                    return $replayed;
                }

                if (! empty($attributes['appointment_id'])) {
                    $appointment = Appointment::query()
                        ->with('service')
                        ->lockForUpdate()
                        ->findOrFail($attributes['appointment_id']);

                    // A concurrent request with the same key may have completed
                    // while this request waited for the appointment lock.
                    if ($replayed = $this->replayed($idempotencyKey, allowedActions: $allowedReplayActions, locking: true)) {
                        $this->assertCreationReplayMatches($replayed, $attributes);

                        return $replayed;
                    }

                    $transaction = $this->singleTransactionForAppointment($appointment);

                    if ($transaction) {
                        $this->assertMutable($transaction);

                        if (! empty($attributes['notes'])) {
                            $transaction->update(['notes' => $attributes['notes']]);
                        }

                        return $this->applyPaymentIfPresent($transaction, $attributes, $actorId);
                    }

                    return $this->createCharge(
                        customerProfileId: $appointment->customer_profile_id,
                        serviceId: $appointment->service_id,
                        appointmentId: $appointment->id,
                        charge: $this->appointmentQuote($appointment),
                        attributes: $attributes,
                        actorId: $actorId,
                        defaultReason: __('Appointment charge created'),
                    );
                }

                return $this->createCharge(
                    customerProfileId: (int) $attributes['customer_profile_id'],
                    serviceId: ! empty($attributes['service_id']) ? (int) $attributes['service_id'] : null,
                    appointmentId: null,
                    charge: $this->decimal($attributes['amount'], 'amount'),
                    attributes: $attributes,
                    actorId: $actorId,
                    defaultReason: __('Manual charge created'),
                );
            }, 3);
        } catch (QueryException $exception) {
            if ($idempotencyKey !== null
                && UniqueConstraintViolation::forColumn($exception, 'idempotency_key')
                && ($replayed = $this->replayed($idempotencyKey, allowedActions: $allowedReplayActions))) {
                $this->assertCreationReplayMatches($replayed, $attributes);

                return $replayed;
            }

            throw $exception;
        }
    }

    /**
     * Correct charge metadata. Payments use recordPayment() so retries cannot
     * accidentally apply a cumulative amount twice.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function correct(Transaction $transaction, array $attributes, int $actorId): Transaction
    {
        return DB::transaction(function () use ($transaction, $attributes, $actorId): Transaction {
            if ($replayed = $this->replayed(
                $attributes['idempotency_key'] ?? null,
                $transaction->id,
                [TransactionAdjustment::ACTION_CORRECTION],
            )) {
                return $replayed;
            }

            $locked = Transaction::query()->lockForUpdate()->findOrFail($transaction->id);

            if ($replayed = $this->replayed(
                $attributes['idempotency_key'] ?? null,
                $locked->id,
                [TransactionAdjustment::ACTION_CORRECTION],
                locking: true,
            )) {
                return $replayed;
            }

            $this->assertMutable($locked);

            if (array_key_exists('appointment_id', $attributes)
                && (int) ($attributes['appointment_id'] ?? 0) !== (int) ($locked->appointment_id ?? 0)) {
                throw ValidationException::withMessages([
                    'appointment_id' => __('The linked appointment is immutable. Create a separate manual charge when no appointment applies.'),
                ]);
            }

            $previousAmount = $this->decimal($locked->amount, 'amount');
            $previousPaid = $this->decimal($locked->amount_paid ?? 0, 'amount_paid');
            $newAmount = $this->decimal($attributes['amount'], 'amount');

            if ($this->cents($newAmount) < $this->cents($previousPaid)) {
                throw ValidationException::withMessages([
                    'amount' => __('The charge cannot be lower than the amount already paid.'),
                ]);
            }

            $updates = [
                'amount' => $newAmount,
                'payment_status' => Transaction::derivedPaymentStatus($newAmount, $previousPaid),
                'notes' => $attributes['notes'] ?? null,
            ];

            if (! $locked->appointment_id) {
                $updates['customer_profile_id'] = (int) $attributes['customer_profile_id'];
                $updates['service_id'] = ! empty($attributes['service_id']) ? (int) $attributes['service_id'] : null;
            }

            $locked->update($updates);
            $this->recordAdjustment(
                transaction: $locked,
                action: TransactionAdjustment::ACTION_CORRECTION,
                previousAmount: $previousAmount,
                newAmount: $newAmount,
                previousPaid: $previousPaid,
                newPaid: $previousPaid,
                paymentDelta: '0.00',
                paymentMethod: $locked->payment_method,
                occurredAt: now(),
                actorId: $actorId,
                reason: $this->reason($attributes, __('Charge corrected')),
                idempotencyKey: $attributes['idempotency_key'] ?? null,
            );

            return $locked->refresh();
        }, 3);
    }

    /** @param array<string, mixed> $payment */
    public function recordPayment(Transaction $transaction, array $payment, int $actorId): Transaction
    {
        return DB::transaction(function () use ($transaction, $payment, $actorId): Transaction {
            if ($replayed = $this->replayed(
                $payment['idempotency_key'] ?? null,
                $transaction->id,
                [TransactionAdjustment::ACTION_PAYMENT],
            )) {
                return $replayed;
            }

            $locked = Transaction::query()->lockForUpdate()->findOrFail($transaction->id);

            if ($replayed = $this->replayed(
                $payment['idempotency_key'] ?? null,
                $locked->id,
                [TransactionAdjustment::ACTION_PAYMENT],
                locking: true,
            )) {
                return $replayed;
            }

            return $this->applyPayment(
                transaction: $locked,
                paymentAmount: $this->decimal($payment['payment_amount'], 'payment_amount'),
                paymentMethod: (string) $payment['payment_method'],
                paidAt: Carbon::parse($payment['paid_at']),
                actorId: $actorId,
                reason: $this->reason($payment, __('Payment recorded')),
                idempotencyKey: $payment['idempotency_key'] ?? null,
            );
        }, 3);
    }

    /** @param array<string, mixed> $payment */
    public function completeAppointment(Appointment $appointment, array $payment, int $actorId): Transaction
    {
        return DB::transaction(function () use ($appointment, $payment, $actorId): Transaction {
            if ($replayed = $this->replayed(
                $payment['idempotency_key'] ?? null,
                allowedActions: [TransactionAdjustment::ACTION_CREATED, TransactionAdjustment::ACTION_PAYMENT],
            )) {
                if ((int) $replayed->appointment_id !== (int) $appointment->id) {
                    throw ValidationException::withMessages([
                        'idempotency_key' => __('This submission token was already used for another transaction.'),
                    ]);
                }

                return $replayed;
            }

            $lockedAppointment = Appointment::query()
                ->with('service')
                ->lockForUpdate()
                ->findOrFail($appointment->id);

            if ($replayed = $this->replayed(
                $payment['idempotency_key'] ?? null,
                allowedActions: [TransactionAdjustment::ACTION_CREATED, TransactionAdjustment::ACTION_PAYMENT],
                locking: true,
            )) {
                if ((int) $replayed->appointment_id !== (int) $lockedAppointment->id) {
                    throw ValidationException::withMessages([
                        'idempotency_key' => __('This submission token was already used for another transaction.'),
                    ]);
                }

                return $replayed;
            }

            $transaction = $this->singleTransactionForAppointment($lockedAppointment);

            if (! $transaction) {
                return $this->createCharge(
                    customerProfileId: $lockedAppointment->customer_profile_id,
                    serviceId: $lockedAppointment->service_id,
                    appointmentId: $lockedAppointment->id,
                    charge: $this->appointmentQuote($lockedAppointment),
                    attributes: $payment,
                    actorId: $actorId,
                    defaultReason: __('Charge created when service was completed'),
                );
            }

            $this->assertMutable($transaction);

            if (! empty($payment['notes'])) {
                $transaction->update(['notes' => $payment['notes']]);
            }

            return $this->applyPaymentIfPresent(
                $transaction,
                $payment,
                $actorId,
                __('Payment recorded when service was completed'),
            );
        }, 3);
    }

    /** @param array<string, mixed> $attributes */
    public function refund(Transaction $transaction, array $attributes, int $actorId): Transaction
    {
        return $this->terminate(
            transaction: $transaction,
            attributes: $attributes,
            actorId: $actorId,
            status: Transaction::PAYMENT_REFUNDED,
            action: TransactionAdjustment::ACTION_REFUND,
        );
    }

    /** @param array<string, mixed> $attributes */
    public function void(Transaction $transaction, array $attributes, int $actorId): Transaction
    {
        return $this->terminate(
            transaction: $transaction,
            attributes: $attributes,
            actorId: $actorId,
            status: Transaction::PAYMENT_VOID,
            action: TransactionAdjustment::ACTION_VOID,
        );
    }

    /** @param array<string, mixed> $attributes */
    private function createCharge(
        int $customerProfileId,
        ?int $serviceId,
        ?int $appointmentId,
        string $charge,
        array $attributes,
        int $actorId,
        string $defaultReason,
    ): Transaction {
        $paymentAmount = $this->decimal($attributes['payment_amount'] ?? 0, 'payment_amount');
        $paymentReceived = $this->cents($paymentAmount) > 0;

        if ($paymentReceived) {
            $this->assertPaymentWithinBalance($charge, '0.00', $paymentAmount);
        }

        $paidAt = $paymentReceived ? Carbon::parse($attributes['paid_at']) : null;
        $paymentMethod = $paymentReceived ? (string) $attributes['payment_method'] : null;
        $transaction = $this->transactionNumbers->create([
            'customer_profile_id' => $customerProfileId,
            'appointment_id' => $appointmentId,
            'service_id' => $serviceId,
            'amount' => $charge,
            'amount_paid' => $paymentAmount,
            'payment_status' => Transaction::derivedPaymentStatus($charge, $paymentAmount),
            'payment_method' => $paymentMethod,
            'paid_at' => $paidAt,
            'recorded_by' => $actorId,
            'notes' => $attributes['notes'] ?? null,
        ]);

        $this->recordAdjustment(
            transaction: $transaction,
            action: TransactionAdjustment::ACTION_CREATED,
            previousAmount: '0.00',
            newAmount: $charge,
            previousPaid: '0.00',
            newPaid: $paymentAmount,
            paymentDelta: $paymentAmount,
            paymentMethod: $paymentMethod,
            occurredAt: $paidAt ?? now(),
            actorId: $actorId,
            reason: $this->reason($attributes, $defaultReason),
            idempotencyKey: $attributes['idempotency_key'] ?? null,
        );

        return $transaction->refresh();
    }

    /** @param array<string, mixed> $attributes */
    private function applyPaymentIfPresent(
        Transaction $transaction,
        array $attributes,
        int $actorId,
        string $defaultReason = 'Payment recorded',
    ): Transaction {
        $paymentAmount = $this->decimal($attributes['payment_amount'] ?? 0, 'payment_amount');

        if ($this->cents($paymentAmount) === 0) {
            return $transaction->refresh();
        }

        return $this->applyPayment(
            transaction: $transaction,
            paymentAmount: $paymentAmount,
            paymentMethod: (string) $attributes['payment_method'],
            paidAt: Carbon::parse($attributes['paid_at']),
            actorId: $actorId,
            reason: $this->reason($attributes, $defaultReason),
            idempotencyKey: $attributes['idempotency_key'] ?? null,
        );
    }

    private function applyPayment(
        Transaction $transaction,
        string $paymentAmount,
        string $paymentMethod,
        Carbon $paidAt,
        int $actorId,
        string $reason,
        ?string $idempotencyKey,
    ): Transaction {
        $this->assertMutable($transaction);
        $previousAmount = $this->decimal($transaction->amount, 'amount');
        $previousPaid = $this->decimal($transaction->amount_paid ?? 0, 'amount_paid');
        $this->assertPaymentWithinBalance($previousAmount, $previousPaid, $paymentAmount);
        $newPaid = $this->fromCents($this->cents($previousPaid) + $this->cents($paymentAmount));

        $transaction->update([
            'amount_paid' => $newPaid,
            'payment_status' => Transaction::derivedPaymentStatus($previousAmount, $newPaid),
            'payment_method' => $paymentMethod,
            'paid_at' => $paidAt,
        ]);

        $this->recordAdjustment(
            transaction: $transaction,
            action: TransactionAdjustment::ACTION_PAYMENT,
            previousAmount: $previousAmount,
            newAmount: $previousAmount,
            previousPaid: $previousPaid,
            newPaid: $newPaid,
            paymentDelta: $paymentAmount,
            paymentMethod: $paymentMethod,
            occurredAt: $paidAt,
            actorId: $actorId,
            reason: $reason,
            idempotencyKey: $idempotencyKey,
        );

        return $transaction->refresh();
    }

    /** @param array<string, mixed> $attributes */
    private function terminate(
        Transaction $transaction,
        array $attributes,
        int $actorId,
        string $status,
        string $action,
    ): Transaction {
        return DB::transaction(function () use ($transaction, $attributes, $actorId, $status, $action): Transaction {
            if ($replayed = $this->replayed(
                $attributes['idempotency_key'] ?? null,
                $transaction->id,
                [$action],
            )) {
                return $replayed;
            }

            $locked = Transaction::query()->lockForUpdate()->findOrFail($transaction->id);

            if ($replayed = $this->replayed(
                $attributes['idempotency_key'] ?? null,
                $locked->id,
                [$action],
                locking: true,
            )) {
                return $replayed;
            }

            $this->assertMutable($locked);
            $previousAmount = $this->decimal($locked->amount, 'amount');
            $previousPaid = $this->decimal($locked->amount_paid ?? 0, 'amount_paid');
            $previousMethod = $locked->payment_method;

            if ($status === Transaction::PAYMENT_REFUNDED && $this->cents($previousPaid) === 0) {
                throw ValidationException::withMessages([
                    'reason' => __('Only a transaction holding a payment can be refunded.'),
                ]);
            }

            $updates = [
                'amount_paid' => '0.00',
                'payment_status' => $status,
            ];

            if ($status === Transaction::PAYMENT_VOID) {
                $updates['payment_method'] = null;
                $updates['paid_at'] = null;
            }

            $locked->update($updates);
            $this->recordAdjustment(
                transaction: $locked,
                action: $action,
                previousAmount: $previousAmount,
                newAmount: $previousAmount,
                previousPaid: $previousPaid,
                newPaid: '0.00',
                paymentDelta: $this->fromCents(-$this->cents($previousPaid)),
                paymentMethod: $previousMethod,
                occurredAt: now(),
                actorId: $actorId,
                reason: trim((string) $attributes['reason']),
                idempotencyKey: $attributes['idempotency_key'] ?? null,
            );

            return $locked->refresh();
        }, 3);
    }

    private function singleTransactionForAppointment(Appointment $appointment): ?Transaction
    {
        $transactions = Transaction::query()
            ->where('appointment_id', $appointment->id)
            ->orderBy('created_at')
            ->orderBy('id')
            ->lockForUpdate()
            ->get();

        if ($transactions->count() > 1) {
            throw ValidationException::withMessages([
                'appointment_id' => __('This appointment has duplicate payment records. Run the approved redundancy repair before recording another payment.'),
            ]);
        }

        return $transactions->first();
    }

    private function appointmentQuote(Appointment $appointment): string
    {
        $quote = $appointment->quoted_amount ?? $appointment->service?->price;

        if ($quote === null || $this->cents((string) $quote) <= 0) {
            throw ValidationException::withMessages([
                'appointment_id' => __('This appointment does not have a valid quoted charge.'),
            ]);
        }

        return $this->decimal($quote, 'amount');
    }

    private function assertMutable(Transaction $transaction): void
    {
        if (in_array($transaction->payment_status, Transaction::TERMINAL_PAYMENT_STATUSES, true)) {
            throw ValidationException::withMessages([
                'payment_status' => __('Refunded and void transactions are immutable.'),
            ]);
        }
    }

    private function assertPaymentWithinBalance(string $charge, string $alreadyPaid, string $payment): void
    {
        $paymentInCents = $this->cents($payment);

        if ($paymentInCents <= 0) {
            throw ValidationException::withMessages([
                'payment_amount' => __('Enter a payment greater than zero.'),
            ]);
        }

        if ($this->cents($alreadyPaid) + $paymentInCents > $this->cents($charge)) {
            throw ValidationException::withMessages([
                'payment_amount' => __('The payment exceeds the remaining balance.'),
            ]);
        }
    }

    /** @param array<int, string> $allowedActions */
    private function replayed(
        mixed $idempotencyKey,
        ?int $transactionId = null,
        array $allowedActions = [],
        bool $locking = false,
    ): ?Transaction {
        $key = trim((string) $idempotencyKey);

        if ($key === '') {
            return null;
        }

        $adjustmentQuery = TransactionAdjustment::query()
            ->with('transaction')
            ->where('idempotency_key', $key);

        if ($locking && DB::transactionLevel() > 0) {
            // A locking read observes the latest committed key on MySQL even
            // when an earlier consistent read would use an older snapshot.
            $adjustmentQuery->lockForUpdate();
        }

        $adjustment = $adjustmentQuery->first();

        if (! $adjustment) {
            return null;
        }

        if ($transactionId !== null && (int) $adjustment->transaction_id !== $transactionId) {
            throw ValidationException::withMessages([
                'idempotency_key' => __('This submission token was already used for another transaction.'),
            ]);
        }

        if ($allowedActions !== [] && ! in_array($adjustment->action, $allowedActions, true)) {
            throw ValidationException::withMessages([
                'idempotency_key' => __('This submission token was already used for a different operation.'),
            ]);
        }

        return $adjustment->transaction;
    }

    /** @param array<string, mixed> $attributes */
    private function assertCreationReplayMatches(Transaction $transaction, array $attributes): void
    {
        $appointmentId = ! empty($attributes['appointment_id']) ? (int) $attributes['appointment_id'] : null;

        if ($appointmentId !== null) {
            if ((int) $transaction->appointment_id !== $appointmentId) {
                throw ValidationException::withMessages([
                    'idempotency_key' => __('This submission token was already used for another appointment.'),
                ]);
            }

            return;
        }

        $sameStandaloneCharge = $transaction->appointment_id === null
            && (int) $transaction->customer_profile_id === (int) $attributes['customer_profile_id']
            && $this->cents($transaction->amount) === $this->cents($attributes['amount']);

        if (! $sameStandaloneCharge) {
            throw ValidationException::withMessages([
                'idempotency_key' => __('This submission token was already used for another transaction.'),
            ]);
        }
    }

    private function recordAdjustment(
        Transaction $transaction,
        string $action,
        string $previousAmount,
        string $newAmount,
        string $previousPaid,
        string $newPaid,
        string $paymentDelta,
        ?string $paymentMethod,
        Carbon $occurredAt,
        int $actorId,
        string $reason,
        ?string $idempotencyKey,
    ): void {
        $transaction->adjustments()->create([
            'action' => $action,
            'previous_amount' => $previousAmount,
            'new_amount' => $newAmount,
            'previous_amount_paid' => $previousPaid,
            'new_amount_paid' => $newPaid,
            'payment_delta' => $paymentDelta,
            'payment_method' => $paymentMethod,
            'occurred_at' => $occurredAt,
            'recorded_by' => $actorId,
            'reason' => $reason,
            'idempotency_key' => $idempotencyKey ?: null,
        ]);
    }

    /** @param array<string, mixed> $attributes */
    private function reason(array $attributes, string $default): string
    {
        $reason = trim((string) ($attributes['reason'] ?? ''));

        return $reason !== '' ? $reason : $default;
    }

    private function decimal(mixed $value, string $field): string
    {
        if (! is_numeric($value)) {
            throw ValidationException::withMessages([$field => __('Enter a valid monetary amount.')]);
        }

        $cents = (int) round((float) $value * 100);

        if ($cents < 0 || $cents > 99999999) {
            throw ValidationException::withMessages([$field => __('Enter an amount between 0 and 999,999.99.')]);
        }

        return $this->fromCents($cents);
    }

    private function cents(string|int|float $value): int
    {
        return (int) round((float) $value * 100);
    }

    private function fromCents(int $cents): string
    {
        return number_format($cents / 100, 2, '.', '');
    }
}
