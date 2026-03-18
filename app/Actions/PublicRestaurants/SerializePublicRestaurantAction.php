<?php

namespace App\Actions\PublicRestaurants;

use App\Models\Restaurant;
use Illuminate\Support\Facades\Storage;

class SerializePublicRestaurantAction
{
    /**
     * @return array<string, mixed>
     */
    public function handle(Restaurant $restaurant): array
    {
        return [
            'slug' => $restaurant->slug,
            'name' => $restaurant->name,
            'description' => $restaurant->description,
            'contacts' => $restaurant->contacts,
            'work_hours' => $restaurant->work_hours,
            'open_time' => $this->trimSeconds($restaurant->open_time),
            'close_time' => $this->trimSeconds($restaurant->close_time),
            'closed_dates' => $this->closedDates($restaurant),
            'logo_url' => $restaurant->logo_path ? Storage::disk('public')->url($restaurant->logo_path) : null,
            'cover_url' => $restaurant->cover_path ? Storage::disk('public')->url($restaurant->cover_path) : null,
        ];
    }

    /**
     * @return list<string>
     */
    private function closedDates(Restaurant $restaurant): array
    {
        if (! is_string($restaurant->closed_dates) || $restaurant->closed_dates === '') {
            return [];
        }

        $decoded = json_decode($restaurant->closed_dates, true);

        return is_array($decoded)
            ? array_values(array_filter($decoded, fn (mixed $date): bool => is_string($date) && $date !== ''))
            : [];
    }

    private function trimSeconds(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        return substr($value, 0, 5);
    }
}
