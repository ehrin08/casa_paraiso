<?php

namespace App\Http\Requests\Admin;

use App\Models\PromotionRule;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PromotionRuleRequest extends FormRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $promotionRule = $this->route('promotionRule');

        return [
            'rfm_segment_id' => ['required', 'integer', Rule::exists('rfm_segments', 'id')],
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('promotion_rules', 'name')
                    ->whereNull('deleted_at')
                    ->ignore($promotionRule instanceof PromotionRule ? $promotionRule->id : null),
            ],
            'description' => ['nullable', 'string', 'max:5000'],
            'suggested_offer' => ['required', 'string', 'max:255'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
