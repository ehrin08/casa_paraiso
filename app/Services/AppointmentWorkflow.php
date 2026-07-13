<?php

namespace App\Services;

use App\Models\Appointment;
use App\Models\AppointmentStatusLog;
use App\Models\Service;
use App\Models\StaffProfile;
use Carbon\CarbonInterface;
use Closure;
use Illuminate\Database\QueryException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AppointmentWorkflow
{
    private const NUMBER_ALLOCATION_ATTEMPTS = 5;

    public function __construct(
        private readonly ScheduleWindowResolver $scheduleWindows,
        private readonly AppointmentNumberGenerator $numberGenerator,
    ) {}

    public function nextAppointmentNumber(): string
    {
        return $this->numberGenerator->next();
    }

    public function scheduledEnd(CarbonInterface $start, Service $service): CarbonInterface
    {
        return $start->copy()->addMinutes($service->duration_minutes);
    }

    public function assertBookableStart(
        CarbonInterface $start,
        Service $service,
        string $field = 'scheduled_start_at',
        bool $mustBeFuture = true,
    ): void {
        $businessHours = $this->scheduleWindows->businessHours();
        $timezone = $businessHours['timezone'];
        $candidate = Carbon::instance($start)->setTimezone($timezone);
        $interval = $businessHours['slot_interval_minutes'];
        $messages = [];

        if ($mustBeFuture && $candidate->lte(now($timezone))) {
            $messages[] = __('Choose a future appointment time.');
        }

        if ($interval < 1 || $candidate->minute % $interval !== 0 || $candidate->second !== 0) {
            $messages[] = __('Appointment times must start on a :minutes-minute interval.', [
                'minutes' => max(1, $interval),
            ]);
        }

        if (! $this->scheduleWindows->withinBusinessHours($candidate, $this->scheduledEnd($candidate, $service))) {
            $messages[] = __('The full service must fit inside business hours (:window).', [
                'window' => $businessHours['window'],
            ]);
        }

        if ($messages !== []) {
            throw ValidationException::withMessages([$field => $messages]);
        }
    }

    public function isStaffEligibleForService(StaffProfile $staffProfile, Service $service): bool
    {
        $performsService = $staffProfile->relationLoaded('services')
            ? $staffProfile->services->contains('id', $service->id)
            : $staffProfile->services()->whereKey($service->id)->exists();

        return ! $staffProfile->trashed()
            && $staffProfile->is_bookable
            && $staffProfile->user?->is_active
            && $performsService;
    }

    public function isStaffAvailable(
        StaffProfile $staffProfile,
        Service $service,
        CarbonInterface $start,
        ?CarbonInterface $end = null,
        ?Appointment $ignoreAppointment = null,
    ): bool {
        $end ??= $this->scheduledEnd($start, $service);

        if (! $this->isStaffEligibleForService($staffProfile, $service)) {
            return false;
        }

        if (! $this->scheduleWindows->covers($staffProfile, $start, $end)) {
            return false;
        }

        return ! $this->hasConfirmedOverlap($staffProfile, $start, $end, $ignoreAppointment);
    }

    public function hasConfirmedOverlap(
        StaffProfile $staffProfile,
        CarbonInterface $start,
        CarbonInterface $end,
        ?Appointment $ignoreAppointment = null,
    ): bool {
        return Appointment::query()
            ->where('staff_profile_id', $staffProfile->id)
            ->where('status', Appointment::STATUS_CONFIRMED)
            ->when($ignoreAppointment, fn ($query) => $query->whereKeyNot($ignoreAppointment->getKey()))
            ->where('scheduled_start_at', '<', $end)
            ->where('scheduled_end_at', '>', $start)
            ->exists();
    }

    /**
     * Atomically assign and confirm a customer-selected slot.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function autoBook(
        array $attributes,
        Service $service,
        CarbonInterface $start,
        ?int $preferredStaffProfileId = null,
        ?int $changedBy = null,
    ): Appointment {
        $this->assertBookableStart($start, $service, 'requested_start_at');

        return $this->withAppointmentNumberRetry(function (string $number) use ($attributes, $service, $start, $preferredStaffProfileId, $changedBy): Appointment {
            return DB::transaction(function () use ($attributes, $service, $start, $preferredStaffProfileId, $changedBy, $number): Appointment {
                $candidateIds = StaffProfile::query()
                    ->eligibleForAppointments()
                    ->offeringService($service)
                    ->orderBy('id')
                    ->pluck('id');

                $staffProfiles = StaffProfile::query()
                    ->with(['user', 'services', 'weeklySchedules', 'scheduleExceptions'])
                    ->whereKey($candidateIds)
                    ->orderBy('id')
                    ->lockForUpdate()
                    ->get();

                $futureBookingCounts = Appointment::query()
                    ->selectRaw('staff_profile_id, COUNT(*) as aggregate')
                    ->whereIn('staff_profile_id', $candidateIds)
                    ->where('status', Appointment::STATUS_CONFIRMED)
                    ->where('scheduled_start_at', '>=', now())
                    ->groupBy('staff_profile_id')
                    ->pluck('aggregate', 'staff_profile_id');

                $available = $staffProfiles
                    ->filter(fn (StaffProfile $staff) => $this->isStaffAvailable($staff, $service, $start))
                    ->sortBy(fn (StaffProfile $staff) => sprintf(
                        '%012d-%012d',
                        (int) ($futureBookingCounts[$staff->id] ?? 0),
                        $staff->id,
                    ));

                $assignedStaff = $preferredStaffProfileId
                    ? $available->firstWhere('id', $preferredStaffProfileId)
                    : null;
                $assignedStaff ??= $available->first();

                if (! $assignedStaff) {
                    throw ValidationException::withMessages([
                        'requested_start_at' => __('Selected calendar slot is no longer available. Choose another date or time.'),
                    ]);
                }

                $end = $this->scheduledEnd($start, $service);
                $appointment = new Appointment;
                $appointment->fill([
                    ...$attributes,
                    'appointment_number' => $number,
                    'service_id' => $service->id,
                    'staff_profile_id' => $assignedStaff->id,
                    'preferred_staff_profile_id' => $preferredStaffProfileId,
                    'requested_start_at' => $start,
                    'scheduled_start_at' => $start,
                    'scheduled_end_at' => $end,
                    'quoted_amount' => $service->price,
                    'updated_by' => $changedBy,
                ]);

                return $this->applyStatus($appointment, Appointment::STATUS_CONFIRMED, $changedBy, __('Automatically confirmed from customer booking'));
            }, 3);
        });
    }

    public function schedule(
        Appointment $appointment,
        StaffProfile $staffProfile,
        Service $service,
        CarbonInterface $start,
        ?int $changedBy = null,
        ?string $reason = null,
    ): Appointment {
        $this->assertBookableStart($start, $service);

        if ($appointment->exists) {
            return $this->scheduleTransaction($appointment, $staffProfile, $service, $start, $changedBy, $reason);
        }

        $attributes = $appointment->getAttributes();
        unset(
            $attributes[$appointment->getKeyName()],
            $attributes['appointment_number'],
            $attributes['status'],
            $attributes['confirmed_at'],
            $attributes['completed_at'],
            $attributes['cancelled_at'],
            $attributes['cancelled_by'],
        );

        return $this->withAppointmentNumberRetry(function (string $number) use ($attributes, $staffProfile, $service, $start, $changedBy, $reason): Appointment {
            $candidate = new Appointment;
            $candidate->fill([
                ...$attributes,
                'appointment_number' => $number,
            ]);

            return $this->scheduleTransaction($candidate, $staffProfile, $service, $start, $changedBy, $reason);
        });
    }

    public function changeStatus(Appointment $appointment, string $status, ?int $changedBy = null, ?string $reason = null): Appointment
    {
        $dirtyAttributes = $appointment->getDirty();
        unset($dirtyAttributes['status']);

        return DB::transaction(function () use ($appointment, $status, $changedBy, $reason, $dirtyAttributes): Appointment {
            $target = $this->lockedTarget($appointment, $dirtyAttributes);

            return $this->applyStatus($target, $status, $changedBy, $reason);
        }, 3);
    }

    public function assertTransitionAllowed(Appointment $appointment, string $status): void
    {
        $allowed = $appointment->exists
            ? (Appointment::STATUS_TRANSITIONS[$appointment->status] ?? [])
            : Appointment::CREATION_STATUSES;

        if (! in_array($status, $allowed, true)) {
            throw ValidationException::withMessages([
                'status' => __('An appointment cannot move from :from to :to.', [
                    'from' => $appointment->status ?: __('a new record'),
                    'to' => $status,
                ]),
            ]);
        }
    }

    private function scheduleTransaction(
        Appointment $appointment,
        StaffProfile $staffProfile,
        Service $service,
        CarbonInterface $start,
        ?int $changedBy,
        ?string $reason,
    ): Appointment {
        $dirtyAttributes = $appointment->getDirty();

        return DB::transaction(function () use ($appointment, $staffProfile, $service, $start, $changedBy, $reason, $dirtyAttributes): Appointment {
            $lockedStaff = StaffProfile::query()
                ->with(['user', 'services', 'weeklySchedules', 'scheduleExceptions'])
                ->lockForUpdate()
                ->findOrFail($staffProfile->id);

            $target = $this->lockedTarget($appointment, $dirtyAttributes);

            $end = $this->scheduledEnd($start, $service);

            if (! $this->isStaffAvailable($lockedStaff, $service, $start, $end, $target->exists ? $target : null)) {
                throw ValidationException::withMessages([
                    'scheduled_start_at' => __('Selected therapist is not available for this schedule.'),
                ]);
            }

            $target->fill([
                'service_id' => $service->id,
                'staff_profile_id' => $lockedStaff->id,
                'scheduled_start_at' => $start,
                'scheduled_end_at' => $end,
                'quoted_amount' => ! $target->exists
                    || (int) $target->getOriginal('service_id') !== (int) $service->id
                    || $target->quoted_amount === null
                        ? $service->price
                        : $target->quoted_amount,
                'updated_by' => $changedBy,
            ]);

            return $this->applyStatus($target, Appointment::STATUS_CONFIRMED, $changedBy, $reason);
        }, 3);
    }

    /**
     * @param  array<string, mixed>  $dirtyAttributes
     */
    private function lockedTarget(Appointment $appointment, array $dirtyAttributes): Appointment
    {
        if (! $appointment->exists) {
            return $appointment;
        }

        $target = Appointment::query()->lockForUpdate()->findOrFail($appointment->id);
        $this->assertLinkedTransactionIdentityUnchanged($target, $dirtyAttributes);
        $target->fill($dirtyAttributes);

        return $target;
    }

    /**
     * A linked transaction snapshots the appointment's customer and service.
     * Run this check after locking the appointment so a concurrent payment
     * cannot be created between the identity check and the appointment update.
     *
     * @param  array<string, mixed>  $dirtyAttributes
     */
    private function assertLinkedTransactionIdentityUnchanged(Appointment $appointment, array $dirtyAttributes): void
    {
        $customerChanged = array_key_exists('customer_profile_id', $dirtyAttributes)
            && (int) $appointment->customer_profile_id !== (int) $dirtyAttributes['customer_profile_id'];
        $serviceChanged = array_key_exists('service_id', $dirtyAttributes)
            && (int) $appointment->service_id !== (int) $dirtyAttributes['service_id'];

        if ((! $customerChanged && ! $serviceChanged) || ! $appointment->transaction()->exists()) {
            return;
        }

        $errors = [];

        if ($customerChanged) {
            $errors['customer_profile_id'] = __('The customer cannot be changed after a transaction is linked to this appointment.');
        }

        if ($serviceChanged) {
            $errors['service_id'] = __('The service cannot be changed after a transaction is linked to this appointment.');
        }

        throw ValidationException::withMessages($errors);
    }

    private function applyStatus(Appointment $appointment, string $status, ?int $changedBy, ?string $reason): Appointment
    {
        $this->assertTransitionAllowed($appointment, $status);

        if (in_array($status, [Appointment::STATUS_COMPLETED, Appointment::STATUS_NO_SHOW], true)
            && (! $appointment->staff_profile_id || ! $appointment->scheduled_start_at || ! $appointment->scheduled_end_at)) {
            throw ValidationException::withMessages([
                'status' => __('Completed and no-show outcomes require a confirmed therapist and schedule.'),
            ]);
        }

        $fromStatus = $appointment->status;
        $now = now();
        $metadata = match ($status) {
            Appointment::STATUS_PENDING => [
                'staff_profile_id' => null,
                'scheduled_start_at' => null,
                'scheduled_end_at' => null,
                'confirmed_at' => null,
                'completed_at' => null,
                'cancelled_at' => null,
                'cancelled_by' => null,
            ],
            Appointment::STATUS_CONFIRMED => [
                'confirmed_at' => $appointment->confirmed_at ?? $now,
                'completed_at' => null,
                'cancelled_at' => null,
                'cancelled_by' => null,
            ],
            Appointment::STATUS_COMPLETED => [
                'completed_at' => $appointment->completed_at ?? $now,
                'cancelled_at' => null,
                'cancelled_by' => null,
            ],
            Appointment::STATUS_CANCELLED => [
                'staff_profile_id' => $fromStatus === Appointment::STATUS_PENDING ? null : $appointment->staff_profile_id,
                'scheduled_start_at' => $fromStatus === Appointment::STATUS_PENDING ? null : $appointment->scheduled_start_at,
                'scheduled_end_at' => $fromStatus === Appointment::STATUS_PENDING ? null : $appointment->scheduled_end_at,
                'confirmed_at' => $fromStatus === Appointment::STATUS_PENDING ? null : $appointment->confirmed_at,
                'completed_at' => null,
                'cancelled_at' => $appointment->cancelled_at ?? $now,
                'cancelled_by' => $appointment->cancelled_by ?? $changedBy,
            ],
            Appointment::STATUS_NO_SHOW => [
                'completed_at' => null,
                'cancelled_at' => null,
                'cancelled_by' => null,
            ],
        };

        $appointment->forceFill([
            ...$metadata,
            'status' => $status,
            'updated_by' => $changedBy,
        ])->save();

        if ($fromStatus !== $status) {
            AppointmentStatusLog::query()->create([
                'appointment_id' => $appointment->id,
                'from_status' => $fromStatus,
                'to_status' => $status,
                'changed_by' => $changedBy,
                'reason' => $reason,
            ]);
        }

        return $appointment;
    }

    /**
     * @param  Closure(string): Appointment  $callback
     */
    private function withAppointmentNumberRetry(Closure $callback): Appointment
    {
        for ($attempt = 1; $attempt <= self::NUMBER_ALLOCATION_ATTEMPTS; $attempt++) {
            try {
                return $callback($this->nextAppointmentNumber());
            } catch (QueryException $exception) {
                if (! UniqueConstraintViolation::forColumn($exception, 'appointment_number')) {
                    throw $exception;
                }
            }
        }

        throw ValidationException::withMessages([
            'appointment_number' => __('The appointment number could not be allocated. Please submit the appointment again.'),
        ]);
    }
}
