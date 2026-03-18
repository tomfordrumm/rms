<?php

namespace Tests\Feature\Console;

use App\Models\Reservation;
use App\Models\Restaurant;
use App\Models\RestaurantTable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GenerateFakeReservationsCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_generates_fake_reservations_for_the_first_restaurant(): void
    {
        $restaurant = Restaurant::factory()->create([
            'name' => 'Casa Azul',
        ]);
        $table = RestaurantTable::factory()->for($restaurant, 'restaurant')->create([
            'capacity' => 6,
            'is_active' => true,
        ]);

        $this->artisan('reservations:fake', ['count' => 5])
            ->expectsOutput('Created 5 fake reservations for restaurant "Casa Azul" (ID '.$restaurant->id.').')
            ->assertSuccessful();

        $this->assertSame(5, Reservation::count());
        $this->assertDatabaseCount('reservations', 5);
        $this->assertDatabaseHas('reservations', [
            'restaurant_id' => $restaurant->id,
            'table_id' => $table->id,
        ]);
    }

    public function test_it_can_target_a_restaurant_by_slug(): void
    {
        $otherRestaurant = Restaurant::factory()->create([
            'slug' => 'other-spot',
        ]);
        RestaurantTable::factory()->for($otherRestaurant, 'restaurant')->create();

        $targetRestaurant = Restaurant::factory()->create([
            'slug' => 'target-spot',
        ]);
        $targetTable = RestaurantTable::factory()->for($targetRestaurant, 'restaurant')->create([
            'capacity' => 8,
        ]);

        $this->artisan('reservations:fake', [
            'count' => 3,
            '--restaurant' => 'target-spot',
        ])->assertSuccessful();

        $this->assertSame(3, Reservation::query()->where('restaurant_id', $targetRestaurant->id)->count());
        $this->assertSame(0, Reservation::query()->where('restaurant_id', $otherRestaurant->id)->count());
        $this->assertDatabaseHas('reservations', [
            'restaurant_id' => $targetRestaurant->id,
            'table_id' => $targetTable->id,
        ]);
    }

    public function test_it_fails_when_restaurant_has_no_tables(): void
    {
        Restaurant::factory()->create([
            'name' => 'No Tables Place',
        ]);

        $this->artisan('reservations:fake', ['count' => 2])
            ->expectsOutput('The selected restaurant has no tables available for fake reservations.')
            ->assertFailed();
    }

    public function test_it_rejects_invalid_count(): void
    {
        $this->artisan('reservations:fake', ['count' => 0])
            ->expectsOutput('Count must be greater than 0.')
            ->assertExitCode(2);
    }
}
