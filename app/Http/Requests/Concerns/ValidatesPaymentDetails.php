<?php

namespace App\Http\Requests\Concerns;

use App\Models\Transaction;
use Closure;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

trait ValidatesPaymentDetails
{
    /**
     * @return array<string, array<int, mixed>>
     */
    protected function paymentDetailRules(bool $paymentRequired = false): array
    {
        return [
            'payment_amount' => [$paymentRequired ? 'required' : 'nullable', 'numeric', 'min:0.01', 'max:999999.99'],
            'payment_method' => ['nullable', Rule::in(Transaction::PAYMENT_METHODS)],
            'paid_at' => ['nullable', 'date', 'before_or_equal:now'],
            'notes' => ['nullable', 'string', 'max:5000'],
            'reason' => ['nullable', 'string', 'max:1000'],
            'idempotency_key' => ['nullable', 'string', 'max:64'],
        ];
    }

    protected function paymentDetailsValidator(): Closure
    {
        return function (Validator $validator): void {
            if (! is_numeric($this->input('payment_amount'))
                || (float) $this->input('payment_amount') <= 0) {
                return;
            }

            if (! $this->filled('payment_method')) {
                $validator->errors()->add('payment_method', __('Select the method used to receive this payment.'));
            }

            if (! $this->filled('paid_at')) {
                $validator->errors()->add('paid_at', __('Enter the date this payment was received.'));
            }
        };
    }
}
