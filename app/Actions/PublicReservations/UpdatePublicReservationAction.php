<?php

namespace App\Actions\PublicReservations;

use App\Models\Reservation;
use App\Models\RestaurantTable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class UpdatePublicReservationAction
{
    /**
     * @param  array{people_count:int,date:string,time:string,table_id:int}  $payload
     */
    public function handle(Reservation $reservation, array $payload): Reservation
    {
        return DB::transaction(function () use ($reservation, $payload): Reservation {
            $table = RestaurantTable::query()
                ->where('restaurant_id', $reservation->restaurant_id)
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
                ->where('restaurant_id', $reservation->restaurant_id)
                ->where('table_id', $table->id)
                ->whereDate('date', $payload['date'])
                ->where('time', $payload['time'])
                ->where('status', Reservation::STATUS_ACTIVE)
                ->whereKeyNot($reservation->id)
                ->exists();

            if ($hasConflict) {
                throw ValidationException::withMessages([
                    'table_id' => 'The selected table is no longer available for this time slot.',
                ]);
            }

            $reservation->update([
                'table_id' => $table->id,
                'people_count' => $payload['people_count'],
                'date' => $payload['date'],
                'time' => $payload['time'],
            ]);

            return $reservation->fresh(['restaurant', 'restaurantTable']);
        });
    }
}
