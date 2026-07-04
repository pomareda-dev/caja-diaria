<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RecurringRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'amount' => ['required', 'numeric', 'not_in:0'],
            'category_id' => [
                'nullable',
                'integer',
                Rule::exists('categories', 'id')->where('user_id', $this->user()->id),
            ],
            'day_of_month' => ['required', 'integer', 'min:1', 'max:31'],
            'start_month' => ['required', 'date_format:Y-m-d'],
            'end_month' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:start_month'],
            'active' => ['nullable', 'boolean'],
        ];
    }
}
