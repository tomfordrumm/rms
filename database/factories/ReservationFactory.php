<?php

namespace Database\Factories;

use App\Models\Reservation;
use App\Models\Restaurant;
use App\Models\RestaurantTable;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Reservation>
 */
class ReservationFactory extends Factory
{
    protected $model = Reservation::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'restaurant_id' => Restaurant::factory(),
            'table_id' => RestaurantTable::factory(),
            'customer_name' => fake()->name(),
            'customer_email' => fake()->safeEmail(),
            'people_count' => fake()->numberBetween(1, 8),
            'date' => fake()->date(),
            'time' => fake()->time(),
            'token' => Str::random(40),
            'status' => Reservation::STATUS_ACTIVE,
        ];
    }
}
