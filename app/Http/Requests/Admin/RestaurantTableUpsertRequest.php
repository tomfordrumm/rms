<?php

namespace App\Http\Requests\Admin;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class RestaurantTableUpsertRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $isActive = $this->input('is_active');

        $this->merge([
            'is_active' => filter_var($isActive, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? false,
        ]);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'capacity' => ['required', 'integer', 'gt:0'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => 'Table name is required.',
            'capacity.required' => 'Capacity is required.',
            'capacity.integer' => 'Capacity must be a whole number.',
            'capacity.gt' => 'Capacity must be greater than 0.',
        ];
    }
}
