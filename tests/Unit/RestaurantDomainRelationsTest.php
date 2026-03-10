<?php

namespace Tests\Unit;

use App\Models\Category;
use App\Models\Dish;
use App\Models\Reservation;
use App\Models\Restaurant;
use App\Models\RestaurantTable;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RestaurantDomainRelationsTest extends TestCase
{
    use RefreshDatabase;

    public function test_models_expose_expected_relationships(): void
    {
        $user = User::factory()->create();
        $restaurant = Restaurant::factory()->create();
        $table = RestaurantTable::factory()->for($restaurant, 'restaurant')->create();
        $reservation = Reservation::query()->create([
            'restaurant_id' => $restaurant->id,
            'table_id' => $table->id,
            'customer_name' => 'John Doe',
            'customer_email' => 'john@example.com',
            'people_count' => 2,
            'date' => '2026-03-11',
            'time' => '18:30:00',
            'token' => 'reservation-token-2',
            'status' => Reservation::STATUS_ACTIVE,
        ]);
        $category = Category::factory()->for($restaurant, 'restaurant')->create();
        $dish = Dish::factory()->for($restaurant, 'restaurant')->create();

        $restaurant->users()->attach($user);
        $category->dishes()->attach($dish);

        $this->assertTrue($user->restaurants->contains($restaurant));
        $this->assertTrue($restaurant->users->contains($user));
        $this->assertTrue($restaurant->tables->contains($table));
        $this->assertTrue($restaurant->reservations->contains($reservation));
        $this->assertTrue($restaurant->categories->contains($category));
        $this->assertTrue($restaurant->dishes->contains($dish));
        $this->assertTrue($table->restaurant->is($restaurant));
        $this->assertTrue($table->reservations->contains($reservation));
        $this->assertTrue($reservation->restaurant->is($restaurant));
        $this->assertTrue($reservation->restaurantTable->is($table));
        $this->assertTrue($category->restaurant->is($restaurant));
        $this->assertTrue($category->dishes->contains($dish));
        $this->assertTrue($dish->restaurant->is($restaurant));
        $this->assertTrue($dish->categories->contains($category));
    }

    public function test_model_casts_match_phase_one_contract(): void
    {
        $restaurant = Restaurant::factory()->create();
        $table = RestaurantTable::factory()->for($restaurant, 'restaurant')->create([
            'is_active' => 1,
        ]);
        $dish = Dish::factory()->for($restaurant, 'restaurant')->create([
            'price' => '12.5',
            'is_active' => 1,
        ]);

        $this->assertTrue($table->is_active);
        $this->assertSame('12.50', $dish->price);
        $this->assertTrue($dish->is_active);
    }
}
