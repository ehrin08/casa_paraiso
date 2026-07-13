<?php

namespace App\Http\Requests;

use App\Models\Appointment;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Validation\Rule;

class AdminAppointmentUpdateRequest extends AppointmentRequest
{
    protected function prepareForValidation(): void
    {
        $scheduledStart = $this->input('scheduled_start_at', $this->input('requested_start_at'));

        if ($scheduledStart) {
            $this->merge([
                'requested_start_at' => $scheduledStart,
                'scheduled_start_at' => $scheduledStart,
            ]);
        }
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        /** @var Appointment $appointment */
        $appointment = $this->route('appointment');

        return [
            'customer_profile_id' => ['required', 'integer', $this->customerProfileRuleForUpdate($appointment)],
            'service_id' => ['required', 'integer', $this->serviceRuleForUpdate($appointment)],
            'staff_profile_id' => ['nullable', 'integer', $this->staffProfileRuleForUpdate($appointment, 'staff_profile_id')],
            'preferred_staff_profile_id' => ['nullable', 'integer', $this->staffProfileRuleForUpdate($appointment, 'preferred_staff_profile_id')],
            'requested_start_at' => ['required', 'date', 'same:scheduled_start_at'],
            'scheduled_start_at' => ['nullable', 'date'],
            'status' => ['required', Rule::in(Appointment::ACTIVE_STATUSES)],
            ...$this->noteRules(),
        ];
    }
}
