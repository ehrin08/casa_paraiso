<?php

namespace App\Services;

use App\Models\Appointment;
use App\Models\Service;
use App\Models\StaffProfile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class AppointmentAvailability
{
    public function __construct(
        private readonly AppointmentWorkflow $workflow,
        private readonly ScheduleWindowResolver $scheduleWindows,
    ) {}

    /**
     * @return array{
     *     month: string,
     *     service_id: int,
     *     preferred_staff_profile_id: int|null,
     *     dates: array<string, array<int, array{starts_at: string, ends_at: string, time: string, label: string, staff_count: int}>>
     * }
     */
    public function month(Service $service, string $month, ?int $preferredStaffProfileId = null): array
    {
        $monthStart = Carbon::createFromFormat('Y-m', $month)->startOfMonth();
        $monthEnd = $monthStart->copy()->endOfMonth();
        $staffProfiles = $this->eligibleStaff($service, $preferredStaffProfileId);
        $dates = [];

        if ($staffProfiles->isEmpty()) {
            return [
                'month' => $monthStart->format('Y-m'),
                'service_id' => $service->id,
                'preferred_staff_profile_id' => $preferredStaffProfileId,
                'dates' => [],
            ];
        }

        $confirmedByStaff = Appointment::query()
            ->whereIn('staff_profile_id', $staffProfiles->pluck('id'))
            ->where('status', Appointment::STATUS_CONFIRMED)
            ->where('scheduled_start_at', '<', $monthEnd->copy()->addDay()->startOfDay())
            ->where('scheduled_end_at', '>', $monthStart)
            ->get()
            ->groupBy('staff_profile_id');

        for ($day = $monthStart->copy(); $day->lte($monthEnd); $day->addDay()) {
            $daySlots = $this->slotsForDate($service, $staffProfiles, $confirmedByStaff, $day);

            if ($daySlots !== []) {
                $dates[$day->toDateString()] = $daySlots;
            }
        }

        return [
            'month' => $monthStart->format('Y-m'),
            'service_id' => $service->id,
            'preferred_staff_profile_id' => $preferredStaffProfileId,
            'dates' => $dates,
        ];
    }

    /**
     * @return Collection<int, StaffProfile>
     */
    private function eligibleStaff(Service $service, ?int $preferredStaffProfileId = null): Collection
    {
        return StaffProfile::query()
            ->with(['user', 'services', 'weeklySchedules', 'scheduleExceptions'])
            ->eligibleForAppointments()
            ->offeringService($service)
            ->when($preferredStaffProfileId, fn ($query) => $query->whereKey($preferredStaffProfileId))
            ->get();
    }

    /**
     * @param  Collection<int, StaffProfile>  $staffProfiles
     * @param  Collection<int, Collection<int, Appointment>>  $confirmedByStaff
     * @return array<int, array{starts_at: string, ends_at: string, time: string, label: string, staff_count: int}>
     */
    private function slotsForDate(Service $service, Collection $staffProfiles, Collection $confirmedByStaff, Carbon $day): array
    {
        $slots = [];
        $business = $this->scheduleWindows->businessWindow($day);
        $slot = $business['start']->copy();
        $interval = $this->scheduleWindows->businessHours()['slot_interval_minutes'];

        while ($slot->lt($business['end'])) {
            $slotEnd = $this->workflow->scheduledEnd($slot, $service);

            if ($slotEnd->gt($business['end'])) {
                break;
            }

            if ($slot->gt(now())) {
                $availableStaffCount = $staffProfiles
                    ->filter(function (StaffProfile $staffProfile) use ($slot, $slotEnd, $confirmedByStaff): bool {
                        if (! $this->scheduleWindows->covers($staffProfile, $slot, $slotEnd)) {
                            return false;
                        }

                        return ! ($confirmedByStaff->get($staffProfile->id, collect()))
                            ->contains(fn (Appointment $appointment) => $appointment->scheduled_start_at?->lt($slotEnd)
                                && $appointment->scheduled_end_at?->gt($slot));
                    })
                    ->count();

                if ($availableStaffCount > 0) {
                    $slots[] = [
                        'starts_at' => $slot->toDateTimeString(),
                        'ends_at' => $slotEnd->toDateTimeString(),
                        'time' => $slot->format('H:i'),
                        'label' => $slot->format('g:i A'),
                        'staff_count' => $availableStaffCount,
                    ];
                }
            }

            $slot->addMinutes($interval);
        }

        return $slots;
    }
}
