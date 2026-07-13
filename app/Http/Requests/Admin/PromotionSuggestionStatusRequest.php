<?php

namespace App\Http\Requests\Admin;

use App\Models\PromotionSuggestion;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PromotionSuggestionStatusRequest extends FormRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'status' => ['required', Rule::in(PromotionSuggestion::STATUSES)],
            'notes' => ['nullable', 'string', 'max:5000'],
        ];
    }
}
