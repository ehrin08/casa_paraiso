<?php

namespace App\Services;

use App\Exceptions\StaffScheduleConflictException;
use App\Models\Appointment;
use App\Models\StaffProfile;

class StaffScheduleConflictGuard
{
    public function __construct(private readonly ScheduleWindowResolver $scheduleWindows)
    {
    }

    public function assertFutureAppointmentsRemainCovered(StaffProfile $staffProfile): void
    {
        $staff = StaffProfile::query()
            ->with(['weeklySchedules', 'scheduleExceptions'])
            ->findOrFail($staffProfile->id);

        $conflicts = Appointment::query()
            ->with('service')
            ->where('staff_profile_id', $staff->id)
            ->where('status', Appointment::STATUS_CONFIRMED)
            ->where('scheduled_start_at', '>=', now())
            ->orderBy('scheduled_start_at')
            ->get()
            ->reject(fn (Appointment $appointment) => $appointment->scheduled_start_at
                && $appointment->scheduled_end_at
                && $this->scheduleWindows->covers($staff, $appointment->scheduled_start_at, $appointment->scheduled_end_at))
            ->map(fn (Appointment $appointment) => [
                'id' => $appointment->id,
                'number' => $appointment->appointment_number,
                'starts_at' => $appointment->scheduled_start_at?->toDateTimeString() ?? '',
            ])
            ->values()
            ->all();

        if ($conflicts !== []) {
            throw new StaffScheduleConflictException($conflicts);
        }
    }
}
