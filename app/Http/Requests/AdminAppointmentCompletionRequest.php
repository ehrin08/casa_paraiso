<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\ValidatesPaymentDetails;
use Illuminate\Foundation\Http\FormRequest;

class AdminAppointmentCompletionRequest extends FormRequest
{
    use ValidatesPaymentDetails;

    public function rules(): array
    {
        return $this->paymentDetailRules();
    }

    public function after(): array
    {
        return [$this->paymentDetailsValidator()];
    }
}
