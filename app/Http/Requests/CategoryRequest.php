<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CategoryRequest extends FormRequest
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
        $category = $this->route('category');

        return [
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('categories')
                    ->where('user_id', $this->user()->id)
                    ->ignore($category),
            ],
            'kind' => [
                'required',
                Rule::in(['expense', 'income', 'transfer']),
            ],
            'monthly_limit' => [
                'nullable',
                'numeric',
                'min:0',
            ],
            'color' => [
                'nullable',
                'string',
                'max:20',
            ],
            'sort_order' => [
                'nullable',
                'integer',
            ],
        ];
    }
}
