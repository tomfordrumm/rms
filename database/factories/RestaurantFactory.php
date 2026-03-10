<?php

namespace Database\Factories;

use App\Models\Restaurant;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Restaurant>
 */
class RestaurantFactory extends Factory
{
    protected $model = Restaurant::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->unique()->company();

        return [
            'name' => $name,
            'description' => fake()->paragraph(),
            'contacts' => fake()->phoneNumber(),
            'work_hours' => 'Mon-Sun 09:00-22:00',
            'open_time' => '09:00:00',
            'close_time' => '22:00:00',
            'closed_dates' => null,
            'logo_path' => null,
            'cover_path' => null,
            'slug' => Str::slug($name).'-'.fake()->unique()->numberBetween(100, 999),
        ];
    }
}
