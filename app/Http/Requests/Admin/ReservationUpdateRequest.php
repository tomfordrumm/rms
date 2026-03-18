<?php

namespace App\Http\Requests\Admin;

use App\Models\Reservation;
use App\Models\RestaurantTable;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class ReservationUpdateRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $reservationId = $this->route('reservation');
        $restaurant = $this->user()?->primaryRestaurant();

        if ($restaurant === null || ! $restaurant->reservations()->whereKey($reservationId)->exists()) {
            throw new ModelNotFoundException();
        }

        $time = (string) $this->input('time');

        if ($time !== '' && preg_match('/^\d{2}:\d{2}$/', $time) === 1) {
            $this->merge([
                'time' => $time.':00',
            ]);
        }
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $restaurantId = $this->user()?->primaryRestaurant()?->id;

        return [
            'date' => ['required', 'date'],
            'time' => ['required', 'date_format:H:i:s'],
            'table_id' => [
                'required',
                'integer',
                Rule::exists(RestaurantTable::class, 'id')
                    ->where('restaurant_id', $restaurantId)
                    ->where('is_active', true),
            ],
            'people_count' => ['required', 'integer', 'gt:0'],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->isNotEmpty()) {
                    return;
                }

                $reservationId = $this->route('reservation');
                $tableId = (int) $this->input('table_id');
                $peopleCount = (int) $this->input('people_count');
                $date = $this->input('date');
                $time = $this->input('time');

                $table = RestaurantTable::query()->find($tableId);

                if ($table === null) {
                    return;
                }

                if ($peopleCount > $table->capacity) {
                    $validator->errors()->add(
                        'people_count',
                        'People count cannot exceed the selected table capacity.',
                    );
                }

                $normalizedTime = substr($time, 0, 5);

                $hasConflict = Reservation::query()
                    ->where('restaurant_id', $table->restaurant_id)
                    ->where('table_id', $tableId)
                    ->whereDate('date', $date)
                    ->where(function ($query) use ($time, $normalizedTime): void {
                        $query->where('time', $time)
                            ->orWhere('time', $normalizedTime);
                    })
                    ->where('status', Reservation::STATUS_ACTIVE)
                    ->whereKeyNot($reservationId)
                    ->exists();

                if ($hasConflict) {
                    $validator->errors()->add(
                        'time',
                        'The selected table is already reserved for this date and time.',
                    );
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
            'date.required' => 'Reservation date is required.',
            'time.required' => 'Reservation time is required.',
            'time.date_format' => 'Time must use the HH:MM format.',
            'table_id.required' => 'Select a table.',
            'table_id.exists' => 'The selected table is invalid or inactive.',
            'people_count.required' => 'People count is required.',
            'people_count.integer' => 'People count must be a whole number.',
            'people_count.gt' => 'People count must be greater than 0.',
        ];
    }
}
