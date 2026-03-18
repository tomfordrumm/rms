<?php

namespace App\Http\Requests;

use App\Actions\PublicReservations\BuildReservationAvailabilityAction;
use App\Models\Restaurant;
use App\Models\RestaurantTable;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class PublicReservationStoreRequest extends FormRequest
{
    private ?Restaurant $restaurantModel = null;

    protected function prepareForValidation(): void
    {
        $this->restaurantModel = Restaurant::query()
            ->where('slug', $this->route('slug'))
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
            'customer_name' => ['required', 'string', 'max:255'],
            'customer_email' => ['required', 'email:rfc', 'max:255'],
            'people_count' => ['required', 'integer', 'min:1'],
            'date' => ['required', 'date_format:Y-m-d'],
            'time' => ['required', 'date_format:H:i'],
            'table_id' => [
                'required',
                'integer',
                Rule::exists(RestaurantTable::class, 'id')
                    ->where('restaurant_id', $this->restaurantModel?->id)
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

                if ($this->restaurantModel === null) {
                    $validator->errors()->add('date', 'Restaurant not found.');

                    return;
                }

                $peopleCount = (int) $this->input('people_count');
                $date = (string) $this->input('date');
                $time = (string) $this->input('time');
                $tableId = (int) $this->input('table_id');

                $availability = app(BuildReservationAvailabilityAction::class)->handle(
                    $this->restaurantModel,
                    $peopleCount,
                    $date,
                    $time,
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

                $selectedSlot = collect($availability['selected_date_slots'])
                    ->firstWhere('value', $time);

                if ($selectedSlot === null) {
                    $validator->errors()->add('time', 'The selected time is unavailable.');
                }

                $selectedTable = collect($availability['selected_time_tables'])
                    ->firstWhere('id', $tableId);

                if ($selectedTable === null) {
                    $validator->errors()->add('table_id', 'The selected table is unavailable.');
                }
            },
        ];
    }

    /**
     * @return array{customer_name:string,customer_email:string,people_count:int,date:string,time:string,table_id:int}
     */
    public function reservationPayload(): array
    {
        return [
            'customer_name' => (string) $this->input('customer_name'),
            'customer_email' => (string) $this->input('customer_email'),
            'people_count' => (int) $this->input('people_count'),
            'date' => (string) $this->input('date'),
            'time' => (string) $this->input('time').':00',
            'table_id' => (int) $this->input('table_id'),
        ];
    }

    public function restaurant(): ?Restaurant
    {
        return $this->restaurantModel;
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'customer_name.required' => 'Your name is required.',
            'customer_email.required' => 'Your email is required.',
            'customer_email.email' => 'Enter a valid email address.',
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
