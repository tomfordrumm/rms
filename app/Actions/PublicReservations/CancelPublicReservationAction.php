<?php

namespace App\Actions\PublicReservations;

use App\Models\Reservation;

class CancelPublicReservationAction
{
    public function handle(Reservation $reservation): void
    {
        if ($reservation->status === Reservation::STATUS_CANCELLED) {
            return;
        }

        $reservation->update([
            'status' => Reservation::STATUS_CANCELLED,
        ]);
    }
}
