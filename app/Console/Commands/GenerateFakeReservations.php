<?php

namespace App\Console\Commands;

use App\Models\Reservation;
use App\Models\Restaurant;
use App\Models\RestaurantTable;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class GenerateFakeReservations extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'reservations:fake
        {count : Number of fake reservations to generate}
        {--restaurant= : Restaurant ID or slug to generate reservations for}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Generate fake reservations for an existing restaurant';

    public function handle(): int
    {
        $count = (int) $this->argument('count');

        if ($count <= 0) {
            $this->error('Count must be greater than 0.');

            return self::INVALID;
        }

        $restaurant = $this->resolveRestaurant();

        if ($restaurant === null) {
            $this->error('Restaurant not found.');

            return self::FAILURE;
        }

        $tables = $this->availableTables($restaurant);

        if ($tables->isEmpty()) {
            $this->error('The selected restaurant has no tables available for fake reservations.');

            return self::FAILURE;
        }

        $created = 0;

        for ($index = 0; $index < $count; $index++) {
            /** @var RestaurantTable $table */
            $table = $tables->random();
            $peopleCount = random_int(1, $table->capacity);
            $date = fake()->dateTimeBetween('now', '+30 days');
            $time = $this->reservationTime();

            Reservation::query()->create([
                'restaurant_id' => $restaurant->id,
                'table_id' => $table->id,
                'customer_name' => fake()->name(),
                'customer_email' => fake()->unique()->safeEmail(),
                'people_count' => $peopleCount,
                'date' => $date->format('Y-m-d'),
                'time' => $time,
                'token' => Str::random(40),
                'status' => fake()->randomElement([
                    Reservation::STATUS_ACTIVE,
                    Reservation::STATUS_ACTIVE,
                    Reservation::STATUS_ACTIVE,
                    Reservation::STATUS_CANCELLED,
                ]),
            ]);

            $created++;
        }

        $this->info(sprintf(
            'Created %d fake reservations for restaurant "%s" (ID %d).',
            $created,
            $restaurant->name,
            $restaurant->id,
        ));

        return self::SUCCESS;
    }

    private function resolveRestaurant(): ?Restaurant
    {
        $identifier = $this->option('restaurant');

        if ($identifier === null) {
            return Restaurant::query()->orderBy('id')->first();
        }

        return Restaurant::query()
            ->whereKey($identifier)
            ->orWhere('slug', $identifier)
            ->first();
    }

    /**
     * @return Collection<int, RestaurantTable>
     */
    private function availableTables(Restaurant $restaurant): Collection
    {
        $activeTables = $restaurant->tables()
            ->where('is_active', true)
            ->get();

        if ($activeTables->isNotEmpty()) {
            return $activeTables;
        }

        return $restaurant->tables()->get();
    }

    private function reservationTime(): string
    {
        $hours = range(12, 22);
        $minutes = [0, 30];

        return sprintf(
            '%02d:%02d:00',
            $hours[array_rand($hours)],
            $minutes[array_rand($minutes)],
        );
    }
}
