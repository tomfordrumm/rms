<?php

namespace App\Actions\PublicReservations;

use App\Models\Restaurant;
use Carbon\CarbonImmutable;

class GenerateReservationSlotsAction
{
    /**
     * @return list<string>
     */
    public function handle(Restaurant $restaurant, CarbonImmutable $date): array
    {
        if (! $this->hasValidHours($restaurant)) {
            return [];
        }

        $opening = $date->setTimeFromTimeString((string) $restaurant->open_time);
        $closing = $date->setTimeFromTimeString((string) $restaurant->close_time);

        if ($closing->lessThanOrEqualTo($opening)) {
            return [];
        }

        $now = CarbonImmutable::now();
        $cursor = $opening;
        $slots = [];

        while ($cursor->addHour()->lessThanOrEqualTo($closing)) {
            if (! $date->isSameDay($now) || ! $cursor->lessThan($now)) {
                $slots[] = $cursor->format('H:i');
            }

            $cursor = $cursor->addHour();
        }

        return $slots;
    }

    private function hasValidHours(Restaurant $restaurant): bool
    {
        return filled($restaurant->open_time) && filled($restaurant->close_time);
    }
}
