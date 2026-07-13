<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\ValidatesPaymentDetails;
use Illuminate\Foundation\Http\FormRequest;

class TransactionPaymentRequest extends FormRequest
{
    use ValidatesPaymentDetails;

    public function rules(): array
    {
        $rules = $this->paymentDetailRules(paymentRequired: true);
        $rules['notes'] = ['prohibited'];

        return $rules;
    }

    public function after(): array
    {
        return [$this->paymentDetailsValidator()];
    }
}
