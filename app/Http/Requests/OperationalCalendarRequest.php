<?php

namespace App\Http\Requests;

use App\Models\Appointment;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class OperationalCalendarRequest extends FormRequest
{
    public function rules(): array
    {
        $rules = [
            'start' => ['required', 'date_format:Y-m-d'],
            'end' => ['required', 'date_format:Y-m-d'],
            'status' => ['nullable', Rule::in(Appointment::ACTIVE_STATUSES)],
        ];

        if ($this->user()?->isStaff()) {
            return $rules;
        }

        return [
            ...$rules,
            'mode' => ['nullable', Rule::in(['bookings', 'availability'])],
            'staff_profile_id' => ['nullable', 'integer', 'exists:staff_profiles,id'],
            'service_id' => ['nullable', 'integer', 'exists:services,id'],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->isNotEmpty()) {
                    return;
                }

                [$start, $end] = $this->range();

                if ($end->lte($start) || $start->diffInDays($end) > 8) {
                    $validator->errors()->add('end', __('Calendar ranges must cover no more than eight days.'));
                }
            },
        ];
    }

    /**
     * @return array{Carbon, Carbon}
     */
    public function range(): array
    {
        return [
            Carbon::createFromFormat('Y-m-d', (string) $this->input('start'))->startOfDay(),
            Carbon::createFromFormat('Y-m-d', (string) $this->input('end'))->startOfDay(),
        ];
    }
}
