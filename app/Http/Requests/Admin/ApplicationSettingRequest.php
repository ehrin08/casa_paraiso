<?php

namespace App\Http\Requests\Admin;

use App\Models\Transaction;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ApplicationSettingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() ?? false;
    }

    public function rules(): array
    {
        return [
            'business_name' => ['required', 'string', 'max:255'],
            'contact_email' => ['nullable', 'email', 'max:255'],
            'contact_phone' => ['nullable', 'string', 'max:50'],
            'business_address' => ['nullable', 'string', 'max:1000'],
            'default_payment_method' => ['required', Rule::in(Transaction::PAYMENT_METHODS)],
        ];
    }
}
