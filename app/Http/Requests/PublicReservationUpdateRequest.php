<?php

namespace App\Http\Requests;

use App\Actions\PublicReservations\BuildReservationAvailabilityAction;
use App\Models\Reservation;
use App\Models\RestaurantTable;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class PublicReservationUpdateRequest extends FormRequest
{
    private ?Reservation $reservationModel = null;

    protected function prepareForValidation(): void
    {
        $slug = (string) $this->route('slug');
        $token = (string) $this->route('token');

        $this->reservationModel = Reservation::query()
            ->where('token', $token)
            ->whereHas('restaurant', fn ($query) => $query->where('slug', $slug))
            ->with(['restaurant', 'restaurantTable'])
            ->first();

        $time = (string) $this->input('time');

        if ($time !== '' && preg_match('/^\d{2}:\d{2}(:\d{2})?$/', $time) === 1) {
            $this->merge([
                'time' => substr($time, 0, 5),
            ]);
        }
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'people_count' => ['required', 'integer', 'min:1'],
            'date' => ['required', 'date_format:Y-m-d'],
            'time' => ['required', 'date_format:H:i'],
            'table_id' => [
                'required',
                'integer',
                Rule::exists(RestaurantTable::class, 'id')
                    ->where('restaurant_id', $this->reservationModel?->restaurant_id)
                    ->where('is_active', true),
            ],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->isNotEmpty()) {
                    return;
                }

                if ($this->reservationModel === null) {
                    $validator->errors()->add('date', 'Reservation not found.');

                    return;
                }

                if ($this->reservationModel->status === Reservation::STATUS_CANCELLED) {
                    $validator->errors()->add('date', 'Cancelled reservations cannot be edited.');

                    return;
                }

                $peopleCount = (int) $this->input('people_count');
                $date = (string) $this->input('date');
                $time = (string) $this->input('time');
                $tableId = (int) $this->input('table_id');

                $availability = app(BuildReservationAvailabilityAction::class)->handle(
                    $this->reservationModel->restaurant,
                    $peopleCount,
                    $date,
                    $time,
                    excludeReservationId: $this->reservationModel->id,
                );

                if ($peopleCount > (int) data_get($availability, 'booking_rules.max_party_size', 0)) {
                    $validator->errors()->add('people_count', 'Party size exceeds the largest available table.');
                }

                if (! in_array($date, $availability['calendar_dates'], true)) {
                    $validator->errors()->add('date', 'Reservation date is outside the available booking window.');

                    return;
                }

                if (in_array($date, $availability['closed_dates'], true)) {
                    $validator->errors()->add('date', 'The restaurant is closed on the selected date.');
                }

                if (! in_array($date, $availability['bookable_dates'], true)) {
                    $validator->errors()->add('date', 'There are no remaining slots available for the selected date.');
                }

                if (! collect($availability['selected_date_slots'])->contains(fn (array $slot): bool => $slot['value'] === $time)) {
                    $validator->errors()->add('time', 'The selected time is unavailable.');
                }

                if (! collect($availability['selected_time_tables'])->contains(fn (array $table): bool => $table['id'] === $tableId)) {
                    $validator->errors()->add('table_id', 'The selected table is unavailable.');
                }
            },
        ];
    }

    /**
     * @return array{people_count:int,date:string,time:string,table_id:int}
     */
    public function reservationPayload(): array
    {
        return [
            'people_count' => (int) $this->input('people_count'),
            'date' => (string) $this->input('date'),
            'time' => (string) $this->input('time').':00',
            'table_id' => (int) $this->input('table_id'),
        ];
    }

    public function reservation(): ?Reservation
    {
        return $this->reservationModel;
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'people_count.required' => 'Party size is required.',
            'people_count.integer' => 'Party size must be a whole number.',
            'people_count.min' => 'Party size must be at least 1.',
            'date.required' => 'Reservation date is required.',
            'date.date_format' => 'Reservation date must use the YYYY-MM-DD format.',
            'time.required' => 'Reservation time is required.',
            'time.date_format' => 'Reservation time must use the HH:MM format.',
            'table_id.required' => 'Select a table.',
            'table_id.exists' => 'The selected table is invalid or inactive.',
        ];
    }
}
