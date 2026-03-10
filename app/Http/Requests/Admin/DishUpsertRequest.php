<?php

namespace App\Http\Requests\Admin;

use App\Models\Category;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class DishUpsertRequest extends FormRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $restaurantId = $this->user()?->primaryRestaurant()?->id;

        return [
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'weight' => ['required', 'string', 'max:255'],
            'price' => ['required', 'numeric', 'gt:0'],
            'image' => ['nullable', 'image', 'mimes:jpeg,jpg,png,webp', 'max:4096'],
            'is_active' => ['nullable', 'boolean'],
            'category_ids' => ['required', 'array', 'min:1'],
            'category_ids.*' => [
                'integer',
                Rule::exists(Category::class, 'id')->where('restaurant_id', $restaurantId),
            ],
        ];
    }

    protected function prepareForValidation(): void
    {
        $categoryIds = collect($this->input('category_ids', []))
            ->filter(static fn (mixed $value): bool => filled($value))
            ->map(static fn (mixed $value): int => (int) $value)
            ->unique()
            ->values()
            ->all();

        $isActive = $this->input('is_active');

        $this->merge([
            'category_ids' => $categoryIds,
            'is_active' => filter_var($isActive, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? false,
        ]);
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => 'Dish name is required.',
            'weight.required' => 'Weight is required.',
            'price.required' => 'Price is required.',
            'price.gt' => 'Price must be greater than 0.',
            'image.image' => 'Image must be a valid image file.',
            'image.max' => 'Image size must not exceed 4 MB.',
            'category_ids.required' => 'Select at least one category.',
            'category_ids.min' => 'Select at least one category.',
            'category_ids.*.exists' => 'One of the selected categories is invalid.',
        ];
    }
}
