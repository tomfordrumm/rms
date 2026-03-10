<?php

namespace App\Http\Requests\Settings;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;
use Illuminate\Support\Str;

class RestaurantUpsertRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $slugSource = $this->input('slug') ?: $this->input('name');
        $slug = Str::slug((string) $slugSource);

        $closedDates = collect($this->input('closed_dates', []))
            ->filter(fn (mixed $date): bool => filled($date))
            ->values()
            ->all();

        $this->merge([
            'slug' => $slug !== '' ? $slug : null,
            'closed_dates' => $closedDates,
        ]);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $restaurantId = $this->user()?->primaryRestaurant()?->id;

        return [
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'slug' => [
                'nullable',
                'string',
                'max:255',
                'alpha_dash',
                Rule::unique('restaurants', 'slug')->ignore($restaurantId),
            ],
            'contacts' => ['nullable', 'string'],
            'work_hours' => ['nullable', 'string'],
            'open_time' => ['required', 'date_format:H:i'],
            'close_time' => ['required', 'date_format:H:i', 'after:open_time'],
            'closed_dates' => ['nullable', 'array'],
            'closed_dates.*' => ['date', 'distinct'],
            'logo' => ['nullable', 'image', 'mimes:jpeg,jpg,png,webp', 'max:4096'],
            'cover' => ['nullable', 'image', 'mimes:jpeg,jpg,png,webp', 'max:6144'],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $closedDates = $this->input('closed_dates', []);

                if (count($closedDates) !== count(array_unique($closedDates))) {
                    $validator->errors()->add('closed_dates', 'Closed dates must not contain duplicates.');
                }
            },
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => 'Restaurant name is required.',
            'slug.unique' => 'This slug is already in use.',
            'open_time.required' => 'Opening time is required.',
            'close_time.required' => 'Closing time is required.',
            'close_time.after' => 'Closing time must be later than opening time.',
            'logo.image' => 'Logo must be a valid image file.',
            'cover.image' => 'Cover must be a valid image file.',
            'closed_dates.*.date' => 'Each closed date must be a valid date.',
            'closed_dates.*.distinct' => 'Closed dates must not contain duplicates.',
        ];
    }
}
