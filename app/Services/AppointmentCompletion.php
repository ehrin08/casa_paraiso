<?php

namespace App\Services;

use App\Models\Appointment;
use App\Models\Transaction;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AppointmentCompletion
{
    public function __construct(
        private readonly AppointmentWorkflow $workflow,
        private readonly PaymentService $payments,
    ) {}

    /** @param array<string, mixed> $payment */
    public function complete(Appointment $appointment, array $payment, int $adminId): Transaction
    {
        return DB::transaction(function () use ($appointment, $payment, $adminId): Transaction {
            $locked = Appointment::query()->with('service')->lockForUpdate()->findOrFail($appointment->id);

            if ($locked->status !== Appointment::STATUS_CONFIRMED) {
                throw ValidationException::withMessages(['status' => __('Only a confirmed appointment can be finished.')]);
            }

            if (! $locked->scheduled_start_at || $locked->scheduled_start_at->isFuture()) {
                throw ValidationException::withMessages(['status' => __('This service can be finished once its scheduled start time is reached.')]);
            }

            $transaction = $this->payments->completeAppointment($locked, $payment, $adminId);

            $this->workflow->changeStatus($locked, Appointment::STATUS_COMPLETED, $adminId, __('Service finished and transaction recorded'));

            return $transaction;
        }, 3);
    }
}
