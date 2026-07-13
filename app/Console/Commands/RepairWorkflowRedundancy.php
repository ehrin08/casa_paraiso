<?php

namespace App\Console\Commands;

use App\Models\Appointment;
use App\Models\AppointmentStatusLog;
use App\Models\Transaction;
use App\Models\TransactionAdjustment;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

class RepairWorkflowRedundancy extends Command
{
    private const PENDING_REASON = 'Closed during immediate-confirmation remediation';

    protected $signature = 'casa:repair-workflow-redundancy
        {--apply : Apply the reviewed repair instead of running read-only}
        {--actor= : Admin or super-admin user ID recorded in audit history}
        {--force : Allow an explicitly approved apply run in production}';

    protected $description = 'Dry-run or apply the approved pending-appointment and duplicate-payment repair.';

    public function handle(): int
    {
        if (! $this->schemaIsReady()) {
            $this->error('Run only the additive payment migrations (2026_07_13_000000 and 2026_07_13_000100) before this command. Do not finalize the unique constraint yet.');

            return self::FAILURE;
        }

        $pending = $this->pendingAppointments();
        $duplicateGroups = $this->duplicateGroups();
        $missingQuotes = Appointment::query()
            ->with('service')
            ->whereNull('quoted_amount')
            ->orderBy('id')
            ->get();
        $missingPaidAmounts = Transaction::query()
            ->with(['appointment.service', 'service'])
            ->whereNull('amount_paid')
            ->orderBy('id')
            ->get();
        $blockers = $this->backfillBlockers($duplicateGroups);

        $this->renderPlan($pending, $duplicateGroups, $missingQuotes, $missingPaidAmounts, $blockers);

        if (! $this->option('apply')) {
            $this->newLine();
            $this->info('Dry-run complete. No data was changed.');

            return self::SUCCESS;
        }

        if (app()->environment('production') && ! $this->option('force')) {
            $this->error('Production apply requires --force in addition to the recorded database approval.');

            return self::FAILURE;
        }

        $actor = $this->repairActor();

        if (! $actor) {
            return self::FAILURE;
        }

        if ($blockers->isNotEmpty()) {
            $this->error('Repair stopped. Resolve the partial-payment blockers listed above, then run the dry-run again.');

            return self::FAILURE;
        }

        DB::transaction(function () use ($actor, $duplicateGroups): void {
            $this->repairPendingAppointments($actor->id);
            $this->backfillAppointmentQuotes();
            $this->repairDuplicateTransactions($duplicateGroups, $actor->id);
            $this->backfillTransactionPayments($actor->id);
            $this->assertRepairComplete();
        }, 3);

        $this->newLine();
        $this->info('Approved workflow redundancy repair applied successfully. Re-running the command is safe.');

        return self::SUCCESS;
    }

    private function schemaIsReady(): bool
    {
        return Schema::hasColumn('appointments', 'quoted_amount')
            && Schema::hasColumn('transactions', 'amount_paid')
            && Schema::hasTable('transaction_adjustments');
    }

    /** @return Collection<int, Appointment> */
    private function pendingAppointments(): Collection
    {
        return Appointment::query()
            ->where('status', Appointment::STATUS_PENDING)
            ->orderBy('requested_start_at')
            ->orderBy('id')
            ->get();
    }

    /** @return Collection<int, object{appointment_id: int, aggregate: int}> */
    private function duplicateGroups(): Collection
    {
        return DB::table('transactions')
            ->select('appointment_id')
            ->selectRaw('COUNT(*) as aggregate')
            ->whereNotNull('appointment_id')
            ->groupBy('appointment_id')
            ->havingRaw('COUNT(*) > 1')
            ->orderBy('appointment_id')
            ->get();
    }

    /**
     * @param  Collection<int, object{appointment_id: int, aggregate: int}>  $duplicateGroups
     * @return Collection<int, array{record: string, reason: string}>
     */
    private function backfillBlockers(Collection $duplicateGroups): Collection
    {
        $duplicateLosers = $this->duplicateLoserIds($duplicateGroups);

        $partialPaymentBlockers = Transaction::query()
            ->with(['appointment.service', 'service'])
            ->whereNull('amount_paid')
            ->where('payment_status', Transaction::PAYMENT_PARTIAL)
            ->whereKeyNot($duplicateLosers)
            ->orderBy('id')
            ->get()
            ->map(function (Transaction $transaction): ?array {
                $quote = $this->legacyQuote($transaction);

                if ($quote === null) {
                    return [
                        'record' => $transaction->transaction_number,
                        'reason' => 'No appointment or service quote is available for the legacy partial payment.',
                    ];
                }

                if ($this->cents($transaction->amount) > $this->cents($quote)) {
                    return [
                        'record' => $transaction->transaction_number,
                        'reason' => 'The legacy payment is greater than the quoted charge.',
                    ];
                }

                return null;
            })
            ->filter()
            ->values();

        $existingStateBlockers = $this->inconsistentPaymentStates()
            ->map(fn (Transaction $transaction) => [
                'record' => $transaction->transaction_number,
                'reason' => 'Existing amount_paid and payment_status values are inconsistent and require review.',
            ]);
        $quoteBlockers = Appointment::query()
            ->with('service')
            ->whereNull('quoted_amount')
            ->get()
            ->filter(fn (Appointment $appointment) => $appointment->service === null || $this->cents($appointment->service->price) <= 0)
            ->map(fn (Appointment $appointment) => [
                'record' => $appointment->appointment_number,
                'reason' => 'No valid service price is available for the appointment quote snapshot.',
            ]);

        return $partialPaymentBlockers
            ->concat($existingStateBlockers)
            ->concat($quoteBlockers)
            ->values();
    }

    /**
     * @param  Collection<int, Appointment>  $pending
     * @param  Collection<int, object{appointment_id: int, aggregate: int}>  $duplicateGroups
     * @param  Collection<int, Appointment>  $missingQuotes
     * @param  Collection<int, Transaction>  $missingPaidAmounts
     * @param  Collection<int, array{record: string, reason: string}>  $blockers
     */
    private function renderPlan(
        Collection $pending,
        Collection $duplicateGroups,
        Collection $missingQuotes,
        Collection $missingPaidAmounts,
        Collection $blockers,
    ): void {
        $this->components->twoColumnDetail('Pending appointments to cancel', (string) $pending->count());
        $this->components->twoColumnDetail('Duplicate appointment-payment groups', (string) $duplicateGroups->count());
        $this->components->twoColumnDetail('Appointment quotes to backfill', (string) $missingQuotes->count());
        $this->components->twoColumnDetail('Transactions with missing payment totals', (string) $missingPaidAmounts->count());
        $this->components->twoColumnDetail('Blocking records requiring review', (string) $blockers->count());

        if ($pending->isNotEmpty()) {
            $this->newLine();
            $this->table(
                ['Appointment', 'Requested start', 'Action'],
                $pending->map(fn (Appointment $appointment) => [
                    $appointment->appointment_number,
                    $appointment->requested_start_at?->toDateTimeString(),
                    'cancel + status log',
                ])->all(),
            );
        }

        if ($duplicateGroups->isNotEmpty()) {
            $rows = Transaction::query()
                ->whereIn('appointment_id', $duplicateGroups->pluck('appointment_id'))
                ->orderBy('appointment_id')
                ->orderBy('created_at')
                ->orderBy('id')
                ->get()
                ->groupBy('appointment_id')
                ->flatMap(fn (Collection $transactions) => $transactions->values()->map(
                    fn (Transaction $transaction, int $index) => [
                        $transaction->appointment_id,
                        $transaction->transaction_number,
                        $transaction->amount,
                        $index === 0 ? 'retain' : 'void + unlink',
                    ],
                ));

            $this->newLine();
            $this->table(['Appointment ID', 'Transaction', 'Legacy amount', 'Action'], $rows->all());
        }

        if ($missingQuotes->isNotEmpty()) {
            $this->newLine();
            $this->table(
                ['Appointment', 'Service', 'Quote to snapshot', 'Action'],
                $missingQuotes->map(fn (Appointment $appointment) => [
                    $appointment->appointment_number,
                    $appointment->service?->name ?? 'Missing service',
                    $appointment->service?->price ?? 'Unavailable',
                    'snapshot current service price',
                ])->all(),
            );
        }

        if ($missingPaidAmounts->isNotEmpty()) {
            $duplicateLoserIds = $this->duplicateLoserIds($duplicateGroups);
            $this->newLine();
            $this->table(
                ['Transaction', 'Legacy status', 'Legacy amount', 'New charge', 'New paid', 'Action'],
                $missingPaidAmounts->map(function (Transaction $transaction) use ($duplicateLoserIds): array {
                    $isDuplicateLoser = in_array($transaction->id, $duplicateLoserIds, true);
                    $legacyAmount = $this->decimal($transaction->amount);
                    $newCharge = $transaction->payment_status === Transaction::PAYMENT_PARTIAL
                        ? ($this->legacyQuote($transaction) ?? 'BLOCKED')
                        : $legacyAmount;
                    $newPaid = in_array($transaction->payment_status, [Transaction::PAYMENT_PAID, Transaction::PAYMENT_PARTIAL], true)
                        ? $legacyAmount
                        : '0.00';

                    return [
                        $transaction->transaction_number,
                        $transaction->payment_status,
                        $legacyAmount,
                        $isDuplicateLoser ? $legacyAmount : $newCharge,
                        $isDuplicateLoser ? '0.00' : $newPaid,
                        $isDuplicateLoser ? 'void + unlink duplicate' : 'backfill payment totals',
                    ];
                })->all(),
            );
        }

        if ($blockers->isNotEmpty()) {
            $this->newLine();
            $this->table(['Record', 'Blocking reason'], $blockers->all());
        }
    }

    private function repairActor(): ?User
    {
        $actorId = filter_var($this->option('actor'), FILTER_VALIDATE_INT);

        if (! $actorId) {
            $this->error('An admin audit actor is required: --actor=<admin-user-id>.');

            return null;
        }

        $actor = User::query()->find($actorId);

        if (! $actor?->isAdmin()) {
            $this->error('The repair actor must be an existing admin or super-admin user.');

            return null;
        }

        return $actor;
    }

    private function repairPendingAppointments(int $actorId): void
    {
        Appointment::query()
            ->where('status', Appointment::STATUS_PENDING)
            ->orderBy('id')
            ->eachById(function (Appointment $appointment) use ($actorId): void {
                $appointment->forceFill([
                    'staff_profile_id' => null,
                    'scheduled_start_at' => null,
                    'scheduled_end_at' => null,
                    'status' => Appointment::STATUS_CANCELLED,
                    'confirmed_at' => null,
                    'completed_at' => null,
                    'cancelled_at' => now(),
                    'cancelled_by' => $actorId,
                    'updated_by' => $actorId,
                ])->save();

                AppointmentStatusLog::query()->firstOrCreate([
                    'appointment_id' => $appointment->id,
                    'from_status' => Appointment::STATUS_PENDING,
                    'to_status' => Appointment::STATUS_CANCELLED,
                    'reason' => self::PENDING_REASON,
                ], [
                    'changed_by' => $actorId,
                ]);
            });
    }

    private function backfillAppointmentQuotes(): void
    {
        Appointment::query()
            ->with('service')
            ->whereNull('quoted_amount')
            ->orderBy('id')
            ->eachById(function (Appointment $appointment): void {
                $price = $appointment->service?->price;

                if ($price === null || $this->cents($price) <= 0) {
                    throw new RuntimeException("Appointment {$appointment->appointment_number} has no valid service price to snapshot.");
                }

                $appointment->forceFill(['quoted_amount' => $this->decimal($price)])->save();
            });
    }

    /** @param Collection<int, object{appointment_id: int, aggregate: int}> $duplicateGroups */
    private function repairDuplicateTransactions(Collection $duplicateGroups, int $actorId): void
    {
        foreach ($duplicateGroups as $group) {
            $transactions = Transaction::query()
                ->where('appointment_id', $group->appointment_id)
                ->orderBy('created_at')
                ->orderBy('id')
                ->lockForUpdate()
                ->get();
            $retained = $transactions->shift();

            foreach ($transactions as $duplicate) {
                $previousPaid = $this->legacyPaidAmount($duplicate);
                $previousMethod = $duplicate->payment_method;
                $reason = "Duplicate appointment payment removed; retained {$retained->transaction_number}.";
                $cleanupNote = "[Workflow redundancy repair: {$reason}]";

                $duplicate->forceFill([
                    'appointment_id' => null,
                    'amount_paid' => '0.00',
                    'payment_status' => Transaction::PAYMENT_VOID,
                    'payment_method' => null,
                    'paid_at' => null,
                    'notes' => trim(implode("\n", array_filter([$duplicate->notes, $cleanupNote]))),
                ])->save();

                TransactionAdjustment::query()->firstOrCreate([
                    'idempotency_key' => "repair:duplicate:{$duplicate->id}",
                ], [
                    'transaction_id' => $duplicate->id,
                    'action' => TransactionAdjustment::ACTION_REPAIR,
                    'previous_amount' => $this->decimal($duplicate->amount),
                    'new_amount' => $this->decimal($duplicate->amount),
                    'previous_amount_paid' => $previousPaid,
                    'new_amount_paid' => '0.00',
                    'payment_delta' => $this->fromCents(-$this->cents($previousPaid)),
                    'payment_method' => $previousMethod,
                    'occurred_at' => now(),
                    'recorded_by' => $actorId,
                    'reason' => $reason,
                ]);
            }
        }
    }

    private function backfillTransactionPayments(int $actorId): void
    {
        Transaction::query()
            ->with(['appointment.service', 'service'])
            ->whereNull('amount_paid')
            ->orderBy('id')
            ->eachById(function (Transaction $transaction) use ($actorId): void {
                $legacyAmount = $this->decimal($transaction->amount);
                $charge = $legacyAmount;
                $paid = match ($transaction->payment_status) {
                    Transaction::PAYMENT_PAID => $legacyAmount,
                    Transaction::PAYMENT_PARTIAL => $legacyAmount,
                    default => '0.00',
                };

                if ($transaction->payment_status === Transaction::PAYMENT_PARTIAL) {
                    $quote = $this->legacyQuote($transaction);

                    if ($quote === null || $this->cents($paid) > $this->cents($quote)) {
                        throw new RuntimeException("Transaction {$transaction->transaction_number} cannot be backfilled safely.");
                    }

                    $charge = $quote;
                }

                $status = in_array($transaction->payment_status, Transaction::TERMINAL_PAYMENT_STATUSES, true)
                    ? $transaction->payment_status
                    : Transaction::derivedPaymentStatus($charge, $paid);
                $clearPaymentMetadata = in_array($status, [Transaction::PAYMENT_UNPAID, Transaction::PAYMENT_VOID], true);

                $transaction->forceFill([
                    'amount' => $charge,
                    'amount_paid' => $paid,
                    'payment_status' => $status,
                    'payment_method' => $clearPaymentMetadata ? null : $transaction->payment_method,
                    'paid_at' => $clearPaymentMetadata ? null : $transaction->paid_at,
                ])->save();

                TransactionAdjustment::query()->firstOrCreate([
                    'idempotency_key' => "repair:backfill:{$transaction->id}",
                ], [
                    'transaction_id' => $transaction->id,
                    'action' => TransactionAdjustment::ACTION_REPAIR,
                    'previous_amount' => $legacyAmount,
                    'new_amount' => $charge,
                    'previous_amount_paid' => '0.00',
                    'new_amount_paid' => $paid,
                    'payment_delta' => '0.00',
                    'payment_method' => $transaction->payment_method,
                    'occurred_at' => $transaction->paid_at ?? now(),
                    'recorded_by' => $actorId,
                    'reason' => 'Legacy payment totals backfilled without recording a new cash movement.',
                ]);
            });
    }

    private function assertRepairComplete(): void
    {
        $duplicates = $this->duplicateGroups()->count();
        $pending = Appointment::query()->where('status', Appointment::STATUS_PENDING)->count();
        $missingQuotes = Appointment::query()->whereNull('quoted_amount')->count();
        $missingPaid = Transaction::query()->whereNull('amount_paid')->count();
        $invalidPaid = Transaction::query()
            ->where(fn ($query) => $query
                ->where('amount_paid', '<', 0)
                ->orWhereColumn('amount_paid', '>', 'amount'))
            ->count();
        $invalidStatuses = $this->inconsistentPaymentStates()->count();

        if ($duplicates || $pending || $missingQuotes || $missingPaid || $invalidPaid || $invalidStatuses) {
            throw new RuntimeException(sprintf(
                'Repair verification failed: pending=%d, missing_quotes=%d, missing_amount_paid=%d, invalid_amount_paid=%d, invalid_payment_statuses=%d, duplicate_links=%d.',
                $pending,
                $missingQuotes,
                $missingPaid,
                $invalidPaid,
                $invalidStatuses,
                $duplicates,
            ));
        }
    }

    /** @return Collection<int, Transaction> */
    private function inconsistentPaymentStates(): Collection
    {
        return Transaction::query()
            ->whereNotNull('amount_paid')
            ->get()
            ->filter(function (Transaction $transaction): bool {
                $amount = $this->cents($transaction->amount);
                $paid = $this->cents($transaction->amount_paid);

                if ($paid < 0 || $paid > $amount) {
                    return true;
                }

                return match ($transaction->payment_status) {
                    Transaction::PAYMENT_UNPAID => $paid !== 0,
                    Transaction::PAYMENT_PARTIAL => $paid <= 0 || $paid >= $amount,
                    Transaction::PAYMENT_PAID => $paid !== $amount,
                    Transaction::PAYMENT_REFUNDED, Transaction::PAYMENT_VOID => $paid !== 0,
                    default => true,
                };
            })
            ->values();
    }

    /** @param Collection<int, object{appointment_id: int, aggregate: int}> $duplicateGroups */
    private function duplicateLoserIds(Collection $duplicateGroups): array
    {
        return Transaction::query()
            ->whereIn('appointment_id', $duplicateGroups->pluck('appointment_id'))
            ->orderBy('appointment_id')
            ->orderBy('created_at')
            ->orderBy('id')
            ->get()
            ->groupBy('appointment_id')
            ->flatMap(fn (Collection $transactions) => $transactions->skip(1)->pluck('id'))
            ->all();
    }

    private function legacyQuote(Transaction $transaction): ?string
    {
        $quote = $transaction->appointment?->quoted_amount
            ?? $transaction->appointment?->service?->price
            ?? $transaction->service?->price;

        return $quote !== null && $this->cents($quote) > 0
            ? $this->decimal($quote)
            : null;
    }

    private function legacyPaidAmount(Transaction $transaction): string
    {
        if ($transaction->amount_paid !== null) {
            return $this->decimal($transaction->amount_paid);
        }

        return in_array($transaction->payment_status, [Transaction::PAYMENT_PAID, Transaction::PAYMENT_PARTIAL], true)
            ? $this->decimal($transaction->amount)
            : '0.00';
    }

    private function decimal(mixed $amount): string
    {
        return $this->fromCents($this->cents($amount));
    }

    private function cents(mixed $amount): int
    {
        return (int) round((float) $amount * 100);
    }

    private function fromCents(int $amount): string
    {
        return number_format($amount / 100, 2, '.', '');
    }
}
