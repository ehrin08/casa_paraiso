<?php

namespace App\Services;

use App\Models\Appointment;
use App\Models\AppointmentStatusLog;
use App\Models\Service;
use App\Models\StaffProfile;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AppointmentWorkflow
{
    public function __construct(private readonly ScheduleWindowResolver $scheduleWindows)
    {
    }

    public function nextAppointmentNumber(): string
    {
        $prefix = 'APT-'.now()->format('Ymd').'-';
        $sequence = 1;

        do {
            $number = $prefix.str_pad((string) $sequence, 4, '0', STR_PAD_LEFT);
            $sequence++;
        } while (Appointment::query()->where('appointment_number', $number)->exists());

        return $number;
    }

    public function scheduledEnd(CarbonInterface $start, Service $service): CarbonInterface
    {
        return $start->copy()->addMinutes($service->duration_minutes);
    }

    public function isStaffEligibleForService(StaffProfile $staffProfile, Service $service): bool
    {
        $performsService = $staffProfile->relationLoaded('services')
            ? $staffProfile->services->contains('id', $service->id)
            : $staffProfile->services()->whereKey($service->id)->exists();

        return $staffProfile->is_bookable
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

    public function schedule(
        Appointment $appointment,
        StaffProfile $staffProfile,
        Service $service,
        CarbonInterface $start,
        ?int $changedBy = null,
        ?string $reason = null,
    ): Appointment {
        $dirtyAttributes = $appointment->getDirty();

        return DB::transaction(function () use ($appointment, $staffProfile, $service, $start, $changedBy, $reason, $dirtyAttributes): Appointment {
            $lockedStaff = StaffProfile::query()
                ->with(['user', 'services', 'weeklySchedules', 'scheduleExceptions'])
                ->lockForUpdate()
                ->findOrFail($staffProfile->id);

            $target = $appointment->exists
                ? Appointment::query()->lockForUpdate()->findOrFail($appointment->id)
                : $appointment;

            if ($appointment->exists) {
                $target->fill($dirtyAttributes);
            }

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
                'updated_by' => $changedBy,
            ]);
            $target->save();

            return $this->changeStatus($target, Appointment::STATUS_CONFIRMED, $changedBy, $reason);
        });
    }

    public function changeStatus(Appointment $appointment, string $status, ?int $changedBy = null, ?string $reason = null): Appointment
    {
        $fromStatus = $appointment->status;

        $appointment->forceFill([
            'status' => $status,
            'updated_by' => $changedBy,
        ]);

        if ($status === Appointment::STATUS_CONFIRMED) {
            $appointment->confirmed_at ??= now();
        }

        if ($status === Appointment::STATUS_COMPLETED) {
            $appointment->completed_at ??= now();
        }

        if ($status === Appointment::STATUS_CANCELLED) {
            $appointment->cancelled_at ??= now();
            $appointment->cancelled_by ??= $changedBy;
        }

        $appointment->save();

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

}
