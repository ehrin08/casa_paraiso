<?php

namespace App\Http\Requests\Admin;

use App\Models\RfmSegment;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class RfmSegmentRequest extends FormRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $segment = $this->route('rfmSegment');

        return [
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('rfm_segments', 'name')->ignore($segment instanceof RfmSegment ? $segment->id : null),
            ],
            'description' => ['nullable', 'string', 'max:5000'],
            'recency_min_days' => ['nullable', 'integer', 'min:0', 'max:36500'],
            'recency_max_days' => ['nullable', 'integer', 'min:0', 'max:36500'],
            'frequency_min' => ['nullable', 'integer', 'min:0', 'max:1000000'],
            'frequency_max' => ['nullable', 'integer', 'min:0', 'max:1000000'],
            'monetary_min' => ['nullable', 'numeric', 'min:0', 'max:99999999.99'],
            'monetary_max' => ['nullable', 'numeric', 'min:0', 'max:99999999.99'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                foreach ([
                    ['recency_min_days', 'recency_max_days', __('Recency maximum must be greater than or equal to the minimum.')],
                    ['frequency_min', 'frequency_max', __('Frequency maximum must be greater than or equal to the minimum.')],
                    ['monetary_min', 'monetary_max', __('Monetary maximum must be greater than or equal to the minimum.')],
                ] as [$minimum, $maximum, $message]) {
                    if ($this->filled($minimum) && $this->filled($maximum) && (float) $this->input($maximum) < (float) $this->input($minimum)) {
                        $validator->errors()->add($maximum, $message);
                    }
                }
            },
        ];
    }
}
