<?php

namespace App\Services;

use App\Exceptions\StaffScheduleConflictException;
use App\Models\Appointment;
use App\Models\StaffProfile;
use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class StaffScheduleConflictGuard
{
    public function __construct(private readonly ScheduleWindowResolver $scheduleWindows) {}

    public function preservingFutureCoverage(StaffProfile $staffProfile, Closure $mutation): void
    {
        DB::transaction(function () use ($staffProfile, $mutation): void {
            $mutation();
            $this->assertFutureAppointmentsRemainCovered($staffProfile);
        });
    }

    public function assertFutureAppointmentsRemainCovered(StaffProfile $staffProfile): void
    {
        $staff = StaffProfile::query()
            ->with(['weeklySchedules', 'scheduleExceptions'])
            ->findOrFail($staffProfile->id);

        $conflicts = $this->futureConfirmedAppointments($staff)
            ->with('service')
            ->get()
            ->reject(fn (Appointment $appointment) => $appointment->scheduled_start_at
                && $appointment->scheduled_end_at
                && $this->scheduleWindows->covers($staff, $appointment->scheduled_start_at, $appointment->scheduled_end_at))
            ->values();

        $this->throwIfConflicts($conflicts);
    }

    public function assertCanMakeUnavailable(StaffProfile $staffProfile): void
    {
        $this->throwIfConflicts($this->futureConfirmedAppointments($staffProfile)->get());
    }

    /**
     * @param  array<int, int>  $removedServiceIds
     */
    public function assertCanRemoveServices(StaffProfile $staffProfile, array $removedServiceIds): void
    {
        if ($removedServiceIds === []) {
            return;
        }

        $this->throwIfConflicts(
            $this->futureConfirmedAppointments($staffProfile)
                ->whereIn('service_id', $removedServiceIds)
                ->get()
        );
    }

    private function futureConfirmedAppointments(StaffProfile $staffProfile): Builder
    {
        return Appointment::query()
            ->where('staff_profile_id', $staffProfile->id)
            ->where('status', Appointment::STATUS_CONFIRMED)
            ->where('scheduled_start_at', '>=', now())
            ->orderBy('scheduled_start_at')
            ->lockForUpdate();
    }

    /**
     * @param  Collection<int, Appointment>  $appointments
     */
    private function throwIfConflicts(Collection $appointments): void
    {
        $conflicts = $appointments
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
