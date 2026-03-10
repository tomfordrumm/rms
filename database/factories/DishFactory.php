<?php

namespace Database\Factories;

use App\Models\Dish;
use App\Models\Restaurant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Dish>
 */
class DishFactory extends Factory
{
    protected $model = Dish::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'restaurant_id' => Restaurant::factory(),
            'name' => fake()->unique()->words(3, true),
            'description' => fake()->optional()->paragraph(),
            'weight' => fake()->numberBetween(100, 500).' g',
            'price' => fake()->randomFloat(2, 5, 100),
            'image_path' => null,
            'is_active' => true,
        ];
    }
}
