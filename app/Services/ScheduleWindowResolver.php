<?php

namespace App\Services;

use App\Models\StaffProfile;
use App\Models\StaffScheduleException;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class ScheduleWindowResolver
{
    /**
     * @return array{summary: string, window: string, timezone: string, opens_at: string, closes_at: string, closes_next_day: bool, slot_interval_minutes: int, availability_selection_minutes: int}
     */
    public function businessHours(): array
    {
        $hours = config('casa.business_hours');

        return [
            'summary' => (string) $hours['summary'],
            'window' => (string) $hours['window'],
            'timezone' => (string) ($hours['timezone'] ?? config('app.timezone')),
            'opens_at' => (string) $hours['opens_at'],
            'closes_at' => (string) $hours['closes_at'],
            'closes_next_day' => (bool) $hours['closes_next_day'],
            'slot_interval_minutes' => (int) $hours['slot_interval_minutes'],
            'availability_selection_minutes' => (int) $hours['availability_selection_minutes'],
        ];
    }

    /**
     * @return array{start: Carbon, end: Carbon}
     */
    public function businessWindow(CarbonInterface $day): array
    {
        $hours = $this->businessHours();
        $timezone = $hours['timezone'];
        $date = Carbon::parse($day, $timezone)->toDateString();
        $start = Carbon::createFromFormat('Y-m-d H:i', $date.' '.$hours['opens_at'], $timezone);
        $end = Carbon::createFromFormat('Y-m-d H:i', $date.' '.$hours['closes_at'], $timezone);

        if ($hours['closes_next_day'] || $end->lte($start)) {
            $end->addDay();
        }

        return ['start' => $start, 'end' => $end];
    }

    /**
     * Resolve the bookable intervals for one therapist and business date.
     *
     * @return Collection<int, array{start: Carbon, end: Carbon}>
     */
    public function effectiveWindows(StaffProfile $staffProfile, CarbonInterface $day): Collection
    {
        $date = Carbon::parse($day)->startOfDay();
        $business = $this->businessWindow($date);

        $weeklySchedules = $staffProfile->relationLoaded('weeklySchedules')
            ? $staffProfile->weeklySchedules
            : $staffProfile->weeklySchedules()->get();

        $exceptions = $staffProfile->relationLoaded('scheduleExceptions')
            ? $staffProfile->scheduleExceptions->filter(fn (StaffScheduleException $exception) => $exception->exception_date?->isSameDay($date))
            : $staffProfile->scheduleExceptions()->whereDate('exception_date', $date->toDateString())->get();

        $windows = collect();

        foreach ($weeklySchedules->where('day_of_week', $date->dayOfWeek)->where('is_available', true) as $schedule) {
            $interval = $this->intervalForDate(
                $date,
                (string) $schedule->start_time,
                (string) $schedule->end_time,
                (bool) $schedule->ends_next_day,
            );

            if ($clamped = $this->clamp($interval, $business)) {
                $windows->push($clamped);
            }
        }

        foreach ($exceptions->where('exception_type', StaffScheduleException::TYPE_AVAILABLE) as $exception) {
            if (! $exception->start_time || ! $exception->end_time) {
                continue;
            }

            $interval = $this->intervalForDate(
                $date,
                (string) $exception->start_time,
                (string) $exception->end_time,
                (bool) $exception->ends_next_day,
            );

            if ($clamped = $this->clamp($interval, $business)) {
                $windows->push($clamped);
            }
        }

        $windows = $this->merge($windows);

        foreach ($exceptions->where('exception_type', StaffScheduleException::TYPE_UNAVAILABLE) as $exception) {
            if (! $exception->start_time || ! $exception->end_time) {
                return collect();
            }

            $blocked = $this->intervalForDate(
                $date,
                (string) $exception->start_time,
                (string) $exception->end_time,
                (bool) $exception->ends_next_day,
            );

            $windows = $this->subtract($windows, $blocked);
        }

        return $this->merge($windows)->values();
    }

    public function covers(StaffProfile $staffProfile, CarbonInterface $start, CarbonInterface $end): bool
    {
        if ($end->lte($start) || ! $this->withinBusinessHours($start, $end)) {
            return false;
        }

        return $this->effectiveWindows($staffProfile, $start)
            ->contains(fn (array $window) => $window['start']->lte($start) && $window['end']->gte($end));
    }

    public function withinBusinessHours(CarbonInterface $start, CarbonInterface $end): bool
    {
        $business = $this->businessWindow($start);

        return $start->gte($business['start']) && $end->lte($business['end']);
    }

    /**
     * @return array{start: Carbon, end: Carbon}
     */
    public function intervalForDate(CarbonInterface $date, string $startTime, string $endTime, bool $endsNextDay = false): array
    {
        $timezone = $this->businessHours()['timezone'];
        $day = Carbon::parse($date, $timezone)->toDateString();
        $start = Carbon::parse($day.' '.substr($startTime, 0, 8), $timezone);
        $end = Carbon::parse($day.' '.substr($endTime, 0, 8), $timezone);

        if ($endsNextDay) {
            $end->addDay();
        }

        return ['start' => $start, 'end' => $end];
    }

    /**
     * @param  array{start: Carbon, end: Carbon}  $interval
     * @param  array{start: Carbon, end: Carbon}  $boundary
     * @return array{start: Carbon, end: Carbon}|null
     */
    private function clamp(array $interval, array $boundary): ?array
    {
        $start = $interval['start']->greaterThan($boundary['start']) ? $interval['start'] : $boundary['start'];
        $end = $interval['end']->lessThan($boundary['end']) ? $interval['end'] : $boundary['end'];

        return $start->lt($end) ? ['start' => $start->copy(), 'end' => $end->copy()] : null;
    }

    /**
     * @param  Collection<int, array{start: Carbon, end: Carbon}>  $windows
     * @return Collection<int, array{start: Carbon, end: Carbon}>
     */
    private function merge(Collection $windows): Collection
    {
        $merged = collect();

        foreach ($windows->sortBy(fn (array $window) => $window['start']->getTimestamp()) as $window) {
            $lastIndex = $merged->keys()->last();

            if ($lastIndex === null || $merged[$lastIndex]['end']->lt($window['start'])) {
                $merged->push(['start' => $window['start']->copy(), 'end' => $window['end']->copy()]);

                continue;
            }

            if ($window['end']->gt($merged[$lastIndex]['end'])) {
                $merged[$lastIndex] = [
                    'start' => $merged[$lastIndex]['start'],
                    'end' => $window['end']->copy(),
                ];
            }
        }

        return $merged;
    }

    /**
     * @param  Collection<int, array{start: Carbon, end: Carbon}>  $windows
     * @param  array{start: Carbon, end: Carbon}  $blocked
     * @return Collection<int, array{start: Carbon, end: Carbon}>
     */
    private function subtract(Collection $windows, array $blocked): Collection
    {
        $remaining = collect();

        foreach ($windows as $window) {
            if ($blocked['start']->gte($window['end']) || $blocked['end']->lte($window['start'])) {
                $remaining->push($window);

                continue;
            }

            if ($blocked['start']->gt($window['start'])) {
                $remaining->push(['start' => $window['start']->copy(), 'end' => $blocked['start']->copy()]);
            }

            if ($blocked['end']->lt($window['end'])) {
                $remaining->push(['start' => $blocked['end']->copy(), 'end' => $window['end']->copy()]);
            }
        }

        return $remaining;
    }
}
