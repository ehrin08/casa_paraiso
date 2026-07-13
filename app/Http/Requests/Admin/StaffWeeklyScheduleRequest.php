<?php

namespace App\Http\Requests\Admin;

use App\Http\Requests\Concerns\ValidatesScheduleInterval;
use App\Models\StaffProfile;
use App\Models\StaffWeeklySchedule;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StaffWeeklyScheduleRequest extends FormRequest
{
    use ValidatesScheduleInterval;

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'day_of_week' => ['required', 'integer', 'between:0,6'],
            ...$this->scheduleIntervalRules(timesRequired: true),
            'is_available' => ['sometimes', 'boolean'],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->isNotEmpty()) {
                    return;
                }

                $staff = $this->route('staff');
                $weeklySchedule = $this->route('weeklySchedule');
                $endsNextDay = $this->boolean('ends_next_day');
                $interval = $this->validateScheduleInterval(
                    $validator,
                    (string) $this->input('start_time'),
                    (string) $this->input('end_time'),
                    $endsNextDay,
                    __('Therapist availability'),
                );

                if (! $interval) {
                    return;
                }

                $startMinutes = $interval['start'];
                $endMinutes = $interval['end'];

                if (! $staff instanceof StaffProfile) {
                    return;
                }

                $hasOverlap = StaffWeeklySchedule::query()
                    ->where('staff_profile_id', $staff->id)
                    ->where('day_of_week', (int) $this->input('day_of_week'))
                    ->when($weeklySchedule instanceof StaffWeeklySchedule, fn ($query) => $query->whereKeyNot($weeklySchedule->getKey()))
                    ->get()
                    ->contains(function (StaffWeeklySchedule $existing) use ($startMinutes, $endMinutes): bool {
                        $existingStart = $this->minutes((string) $existing->start_time);
                        $existingEnd = $this->minutes((string) $existing->end_time) + ($existing->ends_next_day ? 1440 : 0);

                        return $startMinutes < $existingEnd && $endMinutes > $existingStart;
                    });

                if ($hasOverlap) {
                    $validator->errors()->add('start_time', 'This weekly shift overlaps an existing shift for the selected day.');
                }
            },
        ];
    }
}
