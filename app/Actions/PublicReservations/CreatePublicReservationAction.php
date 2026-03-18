<?php

namespace App\Actions\PublicReservations;

use App\Models\Reservation;
use App\Models\Restaurant;
use App\Models\RestaurantTable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CreatePublicReservationAction
{
    /**
     * @param  array{customer_name:string,customer_email:string,people_count:int,date:string,time:string,table_id:int}  $payload
     */
    public function handle(Restaurant $restaurant, array $payload): Reservation
    {
        return DB::transaction(function () use ($restaurant, $payload): Reservation {
            $table = RestaurantTable::query()
                ->where('restaurant_id', $restaurant->id)
                ->where('is_active', true)
                ->lockForUpdate()
                ->find($payload['table_id']);

            if ($table === null) {
                throw ValidationException::withMessages([
                    'table_id' => 'The selected table is unavailable.',
                ]);
            }

            if ($payload['people_count'] > $table->capacity) {
                throw ValidationException::withMessages([
                    'people_count' => 'People count cannot exceed the selected table capacity.',
                ]);
            }

            $hasConflict = Reservation::query()
                ->where('restaurant_id', $restaurant->id)
                ->where('table_id', $table->id)
                ->whereDate('date', $payload['date'])
                ->where('time', $payload['time'])
                ->where('status', Reservation::STATUS_ACTIVE)
                ->exists();

            if ($hasConflict) {
                throw ValidationException::withMessages([
                    'table_id' => 'The selected table is no longer available for this time slot.',
                ]);
            }

            return Reservation::query()->create([
                'restaurant_id' => $restaurant->id,
                'table_id' => $table->id,
                'customer_name' => $payload['customer_name'],
                'customer_email' => $payload['customer_email'],
                'people_count' => $payload['people_count'],
                'date' => $payload['date'],
                'time' => $payload['time'],
                'token' => $this->token(),
                'status' => Reservation::STATUS_ACTIVE,
            ]);
        });
    }

    private function token(): string
    {
        do {
            $token = Str::random(40);
        } while (Reservation::query()->where('token', $token)->exists());

        return $token;
    }
}
