<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AccountRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        $this->merge([
            'sort_order' => $this->input('sort_order') === null || $this->input('sort_order') === ''
                ? 0
                : $this->integer('sort_order'),
        ]);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, array<mixed>|string>
     */
    public function rules(): array
    {
        $account = $this->route('account');

        return [
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('accounts')
                    ->where('user_id', $this->user()->id)
                    ->ignore($account),
            ],
            'kind' => [
                'required',
                Rule::in(['bank', 'wallet', 'cash', 'credit', 'other']),
            ],
            'balance' => [
                'required',
                'numeric',
            ],
            'exclude_from_reconciliation' => [
                'nullable',
                'boolean',
            ],
            'sort_order' => [
                'nullable',
                'integer',
            ],
        ];
    }
}
