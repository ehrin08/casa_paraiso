<?php

namespace App\Services;

use App\Models\Appointment;
use App\Models\StaffProfile;
use App\Models\StaffScheduleException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class AppointmentCalendar
{
    public function __construct(private readonly ScheduleWindowResolver $scheduleWindows) {}

    /**
     * @param  array{staff_profile_id?: int|null, service_id?: int|null, status?: string|null}  $filters
     * @return array<string, mixed>
     */
    public function admin(Carbon $start, Carbon $end, string $mode, array $filters = []): array
    {
        $staffProfiles = StaffProfile::query()
            ->with(['user', 'services', 'weeklySchedules', 'scheduleExceptions'])
            ->eligibleForAppointments()
            ->when($filters['staff_profile_id'] ?? null, fn (Builder $query, int $id) => $query->whereKey($id))
            ->get()
            ->sortBy('user.name')
            ->values();

        $resources = $staffProfiles->map(fn (StaffProfile $staff) => $this->resource($staff));

        if ($mode === 'availability') {
            $events = $this->availabilityEvents($staffProfiles, $start, $end);
            $events = $events->concat($this->bookingBlockers($staffProfiles, $start, $end));
        } else {
            $query = $this->appointmentsInRange(Appointment::query(), $start, $end)
                ->with(['customerProfile.user', 'service', 'staffProfile.user', 'preferredStaffProfile.user'])
                ->when($filters['service_id'] ?? null, fn (Builder $query, int $id) => $query->where('service_id', $id))
                ->when($filters['status'] ?? null, fn (Builder $query, string $status) => $query->where('status', $status))
                ->when($filters['staff_profile_id'] ?? null, fn (Builder $query, int $id) => $query->where(function (Builder $query) use ($id): void {
                    $query->where('staff_profile_id', $id)->orWhere('preferred_staff_profile_id', $id);
                }));

            $availability = $staffProfiles->flatMap(
                fn (StaffProfile $staff) => $this->effectiveAvailabilityEvents($staff, $start, $end),
            );
            $events = $availability->concat(
                $query->get()->map(fn (Appointment $appointment) => $this->appointmentEvent($appointment, 'admin')),
            );
        }

        return $this->payload($start, $end, $resources, $events, $mode);
    }

    /**
     * @return array<string, mixed>
     */
    public function staff(StaffProfile $staffProfile, Carbon $start, Carbon $end, ?string $status = null): array
    {
        $staff = StaffProfile::query()
            ->with(['user', 'services', 'weeklySchedules', 'scheduleExceptions'])
            ->findOrFail($staffProfile->id);
        $query = $this->appointmentsInRange(Appointment::query(), $start, $end)
            ->with(['customerProfile.user', 'service', 'staffProfile.user', 'preferredStaffProfile.user'])
            ->where('staff_profile_id', $staff->id)
            ->when($status, fn (Builder $query, string $value) => $query->where('status', $value));

        $resources = collect([$this->resource($staff)]);

        $events = $this->effectiveAvailabilityEvents($staff, $start, $end)
            ->concat($query->get()->map(fn (Appointment $appointment) => $this->appointmentEvent($appointment, 'staff')));

        return $this->payload($start, $end, $resources, $events, 'bookings');
    }

    /**
     * @return array<string, mixed>
     */
    public function customer(int $customerProfileId, Carbon $start, Carbon $end, ?string $status = null): array
    {
        $appointments = $this->appointmentsInRange(Appointment::query(), $start, $end)
            ->with(['service', 'staffProfile.user', 'preferredStaffProfile.user'])
            ->where('customer_profile_id', $customerProfileId)
            ->when($status, fn (Builder $query, string $value) => $query->where('status', $value))
            ->get();

        return $this->payload(
            $start,
            $end,
            collect(),
            $appointments->map(fn (Appointment $appointment) => $this->appointmentEvent($appointment, 'customer')),
            'month',
        );
    }

    private function appointmentsInRange(Builder $query, Carbon $start, Carbon $end): Builder
    {
        return $query
            ->whereIn('status', Appointment::ACTIVE_STATUSES)
            ->whereRaw('COALESCE(scheduled_start_at, requested_start_at) >= ?', [$start])
            ->whereRaw('COALESCE(scheduled_start_at, requested_start_at) < ?', [$end]);
    }

    /**
     * @param  Collection<int, StaffProfile>  $staffProfiles
     * @return Collection<int, array<string, mixed>>
     */
    private function availabilityEvents(Collection $staffProfiles, Carbon $start, Carbon $end): Collection
    {
        $events = collect();

        for ($day = $start->copy()->startOfDay(); $day->lt($end); $day->addDay()) {
            $business = $this->scheduleWindows->businessWindow($day);

            foreach ($staffProfiles as $staff) {
                foreach ($staff->weeklySchedules->where('day_of_week', $day->dayOfWeek)->where('is_available', true) as $schedule) {
                    $interval = $this->scheduleWindows->intervalForDate($day, (string) $schedule->start_time, (string) $schedule->end_time, (bool) $schedule->ends_next_day);
                    $event = $this->availabilityEvent($staff, 'weekly_availability', $interval, __('Recurring availability'), false, $schedule->id);

                    if ($event['ends_at'] > $business['start']->toIso8601String() && $event['starts_at'] < $business['end']->toIso8601String()) {
                        $events->push($event);
                    }
                }

                foreach ($staff->scheduleExceptions->filter(fn (StaffScheduleException $exception) => $exception->exception_date?->isSameDay($day)) as $exception) {
                    $interval = $exception->start_time && $exception->end_time
                        ? $this->scheduleWindows->intervalForDate($day, (string) $exception->start_time, (string) $exception->end_time, (bool) $exception->ends_next_day)
                        : $business;
                    $kind = $exception->exception_type === StaffScheduleException::TYPE_AVAILABLE
                        ? 'available_exception'
                        : 'unavailable_exception';
                    $title = $exception->exception_type === StaffScheduleException::TYPE_AVAILABLE
                        ? __('One-off availability')
                        : __('Unavailable');

                    $events->push($this->availabilityEvent($staff, $kind, $interval, $title, false, $exception->id, $exception->reason));
                }
            }
        }

        return $events;
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function effectiveAvailabilityEvents(StaffProfile $staff, Carbon $start, Carbon $end): Collection
    {
        $events = collect();

        for ($day = $start->copy()->startOfDay(); $day->lt($end); $day->addDay()) {
            foreach ($this->scheduleWindows->effectiveWindows($staff, $day) as $index => $window) {
                $events->push($this->availabilityEvent($staff, 'availability', $window, __('Available'), true, $index));
            }
        }

        return $events;
    }

    /**
     * @param  Collection<int, StaffProfile>  $staffProfiles
     * @return Collection<int, array<string, mixed>>
     */
    private function bookingBlockers(Collection $staffProfiles, Carbon $start, Carbon $end): Collection
    {
        return Appointment::query()
            ->with(['service', 'customerProfile.user', 'staffProfile.user'])
            ->whereIn('staff_profile_id', $staffProfiles->pluck('id'))
            ->where('status', Appointment::STATUS_CONFIRMED)
            ->where('scheduled_start_at', '<', $end)
            ->where('scheduled_end_at', '>', $start)
            ->get()
            ->map(function (Appointment $appointment): array {
                $event = $this->appointmentEvent($appointment, 'admin');
                $event['kind'] = 'booking_blocker';
                $event['read_only'] = true;

                return $event;
            });
    }

    /**
     * @param  array{start: Carbon, end: Carbon}  $interval
     * @return array<string, mixed>
     */
    private function availabilityEvent(
        StaffProfile $staff,
        string $kind,
        array $interval,
        string $title,
        bool $readOnly,
        int $sourceId,
        ?string $subtitle = null,
    ): array {
        $detailUrl = match ($kind) {
            'weekly_availability' => route('admin.staff.weekly-schedules.edit', [$staff, $sourceId]),
            'available_exception', 'unavailable_exception' => route('admin.staff.schedule-exceptions.edit', [$staff, $sourceId]),
            default => null,
        };

        return [
            'id' => $kind.'-'.$staff->id.'-'.$sourceId.'-'.$interval['start']->format('Ymd'),
            'kind' => $kind,
            'appointment_id' => null,
            'resource_id' => (string) $staff->id,
            'staff_profile_id' => $staff->id,
            'starts_at' => $interval['start']->toIso8601String(),
            'ends_at' => $interval['end']->toIso8601String(),
            'status' => $kind === 'unavailable_exception' ? 'unavailable' : 'available',
            'title' => $title,
            'subtitle' => $subtitle,
            'detail_url' => $detailUrl,
            'read_only' => $readOnly,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function appointmentEvent(Appointment $appointment, string $audience): array
    {
        $startsAt = $appointment->status === Appointment::STATUS_PENDING
            ? $appointment->requested_start_at
            : ($appointment->scheduled_start_at ?? $appointment->requested_start_at);
        $endsAt = $appointment->scheduled_end_at
            ?? $startsAt?->copy()->addMinutes($appointment->service?->duration_minutes ?? 60);
        $customerName = $appointment->customerProfile?->user?->name;
        $serviceName = $appointment->service?->name ?? __('Spa service');
        $preferredName = $appointment->preferredStaffProfile?->user?->name;
        $staffName = $appointment->staffProfile?->user?->name;

        return [
            'id' => 'appointment-'.$appointment->id,
            'kind' => $appointment->status === Appointment::STATUS_PENDING ? 'request' : 'appointment',
            'appointment_id' => $appointment->id,
            'resource_id' => $appointment->status === Appointment::STATUS_PENDING
                ? 'requests'
                : (string) ($appointment->staff_profile_id ?? 'requests'),
            'staff_profile_id' => $appointment->staff_profile_id,
            'starts_at' => $startsAt?->toIso8601String(),
            'ends_at' => $endsAt?->toIso8601String(),
            'status' => $appointment->status,
            'title' => $audience === 'customer' ? $serviceName : ($customerName ?: $appointment->appointment_number),
            'subtitle' => $audience === 'customer'
                ? ($staffName ?: __('Awaiting therapist assignment'))
                : $serviceName,
            'appointment_number' => $appointment->appointment_number,
            'service_name' => $serviceName,
            'customer_name' => $audience === 'customer' ? null : $customerName,
            'preferred_staff_name' => $preferredName,
            'detail_url' => route($audience.'.appointments.show', $appointment),
            'read_only' => false,
        ];
    }

    /**
     * @return array{id: string, name: string, subtitle: string}
     */
    private function resource(StaffProfile $staff): array
    {
        return [
            'id' => (string) $staff->id,
            'name' => $staff->user?->name ?? __('Therapist'),
            'subtitle' => $staff->specialization ?: $staff->position ?: __('Spa therapist'),
        ];
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $resources
     * @param  Collection<int, array<string, mixed>>  $events
     * @return array<string, mixed>
     */
    private function payload(Carbon $start, Carbon $end, Collection $resources, Collection $events, string $mode): array
    {
        $businessHours = $this->scheduleWindows->businessHours();

        return [
            'range' => [
                'start' => $start->toDateString(),
                'end' => $end->toDateString(),
                'timezone' => $businessHours['timezone'],
            ],
            'mode' => $mode,
            'business_hours' => [
                'opens_at' => $businessHours['opens_at'],
                'closes_at' => $businessHours['closes_at'],
                'closes_next_day' => $businessHours['closes_next_day'],
                'slot_interval_minutes' => $businessHours['slot_interval_minutes'],
                'availability_selection_minutes' => $businessHours['availability_selection_minutes'],
            ],
            'resources' => $resources->values()->all(),
            'events' => $events->filter(fn (array $event) => $event['starts_at'] && $event['ends_at'])->values()->all(),
        ];
    }
}
