<?php

namespace App\Http\Requests\Concerns;

use Illuminate\Validation\Validator;

trait ValidatesScheduleInterval
{
    /**
     * @return array{
     *     start_time: array{string, string},
     *     end_time: array{string, string},
     *     ends_next_day: array{string, string}
     * }
     */
    protected function scheduleIntervalRules(bool $timesRequired): array
    {
        $timePresenceRule = $timesRequired ? 'required' : 'nullable';

        return [
            'start_time' => [$timePresenceRule, 'date_format:H:i'],
            'end_time' => [$timePresenceRule, 'date_format:H:i'],
            'ends_next_day' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * @return array{start: int, end: int}|null
     */
    protected function validateScheduleInterval(
        Validator $validator,
        string $startTime,
        string $endTime,
        bool $endsNextDay,
        string $intervalName,
    ): ?array {
        $businessHours = config('casa.business_hours');
        $startMinutes = $this->minutes($startTime);
        $endMinutes = $this->minutes($endTime) + ($endsNextDay ? 1440 : 0);
        $openingMinutes = $this->minutes((string) $businessHours['opens_at']);
        $closingTime = substr((string) $businessHours['closes_at'], 0, 5);

        if ($startMinutes < $openingMinutes) {
            $validator->errors()->add('start_time', __(':name must begin within business hours (:window).', [
                'name' => $intervalName,
                'window' => $businessHours['window'],
            ]));

            return null;
        }

        if ($endsNextDay && substr($endTime, 0, 5) !== $closingTime) {
            $validator->errors()->add('end_time', __('A next-day :name must end at the configured closing time.', [
                'name' => $intervalName,
            ]));

            return null;
        }

        if ($endMinutes <= $startMinutes) {
            $validator->errors()->add('end_time', __('The end time must be after the start time.'));

            return null;
        }

        return ['start' => $startMinutes, 'end' => $endMinutes];
    }

    protected function minutes(string $time): int
    {
        [$hour, $minute] = array_map('intval', explode(':', substr($time, 0, 5)));

        return ($hour * 60) + $minute;
    }
}
