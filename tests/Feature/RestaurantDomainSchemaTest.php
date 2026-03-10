<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Dish;
use App\Models\Reservation;
use App\Models\Restaurant;
use App\Models\RestaurantTable;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class RestaurantDomainSchemaTest extends TestCase
{
    use RefreshDatabase;

    public function test_restaurant_domain_tables_have_expected_columns(): void
    {
        $this->assertTrue(Schema::hasTable('restaurants'));
        $this->assertTrue(Schema::hasTable('restaurant_user'));
        $this->assertTrue(Schema::hasTable('restaurant_tables'));
        $this->assertTrue(Schema::hasTable('reservations'));
        $this->assertTrue(Schema::hasTable('categories'));
        $this->assertTrue(Schema::hasTable('dishes'));
        $this->assertTrue(Schema::hasTable('category_dish'));

        $this->assertTrue(Schema::hasColumns('restaurants', [
            'name',
            'description',
            'contacts',
            'work_hours',
            'open_time',
            'close_time',
            'closed_dates',
            'logo_path',
            'cover_path',
            'slug',
        ]));

        $this->assertTrue(Schema::hasColumns('reservations', [
            'restaurant_id',
            'table_id',
            'customer_name',
            'customer_email',
            'people_count',
            'date',
            'time',
            'token',
            'status',
        ]));
    }

    public function test_deleting_a_restaurant_cascades_to_related_records(): void
    {
        $user = User::factory()->create();
        $restaurant = Restaurant::factory()->create();
        $table = RestaurantTable::factory()->for($restaurant, 'restaurant')->create();
        $reservation = Reservation::query()->create([
            'restaurant_id' => $restaurant->id,
            'table_id' => $table->id,
            'customer_name' => 'Jane Doe',
            'customer_email' => 'jane@example.com',
            'people_count' => 4,
            'date' => '2026-03-10',
            'time' => '19:00:00',
            'token' => 'reservation-token-1',
            'status' => Reservation::STATUS_ACTIVE,
        ]);
        $category = Category::factory()->for($restaurant, 'restaurant')->create();
        $dish = Dish::factory()->for($restaurant, 'restaurant')->create();

        $restaurant->users()->attach($user);
        $category->dishes()->attach($dish);

        $restaurant->delete();

        $this->assertDatabaseMissing('restaurants', ['id' => $restaurant->id]);
        $this->assertDatabaseMissing('restaurant_tables', ['id' => $table->id]);
        $this->assertDatabaseMissing('reservations', ['id' => $reservation->id]);
        $this->assertDatabaseMissing('categories', ['id' => $category->id]);
        $this->assertDatabaseMissing('dishes', ['id' => $dish->id]);
        $this->assertDatabaseMissing('restaurant_user', [
            'restaurant_id' => $restaurant->id,
            'user_id' => $user->id,
        ]);
        $this->assertDatabaseMissing('category_dish', [
            'category_id' => $category->id,
            'dish_id' => $dish->id,
        ]);
    }

    public function test_restaurant_user_pivot_rejects_duplicate_pairs(): void
    {
        $user = User::factory()->create();
        $restaurant = Restaurant::factory()->create();

        DB::table('restaurant_user')->insert([
            'restaurant_id' => $restaurant->id,
            'user_id' => $user->id,
        ]);

        $this->expectException(QueryException::class);

        DB::table('restaurant_user')->insert([
            'restaurant_id' => $restaurant->id,
            'user_id' => $user->id,
        ]);
    }

    public function test_category_dish_pivot_rejects_duplicate_pairs(): void
    {
        $restaurant = Restaurant::factory()->create();
        $category = Category::factory()->for($restaurant, 'restaurant')->create();
        $dish = Dish::factory()->for($restaurant, 'restaurant')->create();

        DB::table('category_dish')->insert([
            'category_id' => $category->id,
            'dish_id' => $dish->id,
        ]);

        $this->expectException(QueryException::class);

        DB::table('category_dish')->insert([
            'category_id' => $category->id,
            'dish_id' => $dish->id,
        ]);
    }
}
