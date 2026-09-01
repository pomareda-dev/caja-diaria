<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreDebtRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'principal_amount' => ['required', 'numeric', 'gt:0'],
            'disbursement_date' => ['required', 'date'],
            'installment_amount' => ['required', 'numeric', 'gt:0'],
            'installments_count' => ['required', 'integer', 'min:1', 'max:360'],
            'payment_dates' => ['required', 'array', 'min:1'],
            'payment_dates.*' => ['date'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function ($validator) {
            $paymentDates = $this->input('payment_dates', []);
            $installmentsCount = $this->input('installments_count');

            if ($installmentsCount && count($paymentDates) !== (int) $installmentsCount) {
                $validator->errors()->add(
                    'payment_dates',
                    'The number of payment dates must match the installments count.'
                );
            }
        });
    }
}
