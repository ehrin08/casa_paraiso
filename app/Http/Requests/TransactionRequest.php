<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\ValidatesPaymentDetails;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class TransactionRequest extends FormRequest
{
    use ValidatesPaymentDetails;

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $paymentRules = $this->paymentDetailRules();

        if ($this->isMethod('patch')) {
            $paymentRules['payment_amount'] = ['prohibited'];
            $paymentRules['payment_method'] = ['prohibited'];
            $paymentRules['paid_at'] = ['prohibited'];
            $paymentRules['reason'] = ['required', 'string', 'max:1000'];
        }

        return [
            'customer_profile_id' => ['required_without:appointment_id', 'nullable', 'integer', Rule::exists('customer_profiles', 'id')],
            'appointment_id' => ['nullable', 'integer', Rule::exists('appointments', 'id')],
            'service_id' => ['nullable', 'integer', Rule::exists('services', 'id')],
            'amount' => [
                $this->isMethod('patch') ? 'required' : 'required_without:appointment_id',
                'nullable',
                'numeric',
                'min:0.01',
                'max:999999.99',
            ],
            ...$paymentRules,
        ];
    }

    /**
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [$this->paymentDetailsValidator()];
    }
}
