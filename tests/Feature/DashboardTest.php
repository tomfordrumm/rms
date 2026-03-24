<?php

namespace Tests\Feature;

use App\Models\Dish;
use App\Models\Reservation;
use App\Models\Restaurant;
use App\Models\RestaurantTable;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_redirected_to_the_login_page(): void
    {
        $response = $this->get(route('dashboard'));
        $response->assertRedirect(route('login'));
    }

    public function test_authenticated_users_can_visit_the_dashboard(): void
    {
        [$user] = $this->actingUserWithRestaurant();

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Dashboard')
                ->has('stats')
                ->has('reservation_trend', 30),
            );
    }

    public function test_dashboard_counts_are_scoped_to_the_authenticated_users_restaurant(): void
    {
        CarbonImmutable::setTestNow('2026-03-24 12:00:00');

        [$user, $restaurant] = $this->actingUserWithRestaurant();
        $table = RestaurantTable::factory()->for($restaurant, 'restaurant')->create();

        Dish::factory()->count(3)->for($restaurant, 'restaurant')->create();
        RestaurantTable::factory()->count(2)->for($restaurant, 'restaurant')->create();

        Reservation::factory()->create([
            'restaurant_id' => $restaurant->id,
            'table_id' => $table->id,
            'date' => '2026-03-24',
            'status' => Reservation::STATUS_ACTIVE,
        ]);

        $foreignRestaurant = Restaurant::factory()->create();
        $foreignTable = RestaurantTable::factory()->for($foreignRestaurant, 'restaurant')->create();

        Dish::factory()->count(5)->for($foreignRestaurant, 'restaurant')->create();
        RestaurantTable::factory()->count(4)->for($foreignRestaurant, 'restaurant')->create();
        Reservation::factory()->count(2)->create([
            'restaurant_id' => $foreignRestaurant->id,
            'table_id' => $foreignTable->id,
            'date' => '2026-03-24',
            'status' => Reservation::STATUS_ACTIVE,
        ]);

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('stats.dishes_count', 3)
                ->where('stats.tables_count', 3)
                ->where('stats.active_reservations_count', 1),
            );
    }

    public function test_active_reservations_count_only_includes_active_reservations_from_today_onward(): void
    {
        CarbonImmutable::setTestNow('2026-03-24 12:00:00');

        [$user, $restaurant] = $this->actingUserWithRestaurant();
        $table = RestaurantTable::factory()->for($restaurant, 'restaurant')->create();

        Reservation::factory()->create([
            'restaurant_id' => $restaurant->id,
            'table_id' => $table->id,
            'date' => '2026-03-23',
            'status' => Reservation::STATUS_ACTIVE,
        ]);
        Reservation::factory()->create([
            'restaurant_id' => $restaurant->id,
            'table_id' => $table->id,
            'date' => '2026-03-24',
            'status' => Reservation::STATUS_ACTIVE,
        ]);
        Reservation::factory()->create([
            'restaurant_id' => $restaurant->id,
            'table_id' => $table->id,
            'date' => '2026-03-27',
            'status' => Reservation::STATUS_ACTIVE,
        ]);
        Reservation::factory()->create([
            'restaurant_id' => $restaurant->id,
            'table_id' => $table->id,
            'date' => '2026-03-28',
            'status' => Reservation::STATUS_CANCELLED,
        ]);

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('stats.active_reservations_count', 2),
            );
    }

    public function test_reservation_trend_contains_30_days_and_counts_only_active_reservations_by_reservation_date(): void
    {
        CarbonImmutable::setTestNow('2026-03-24 12:00:00');

        [$user, $restaurant] = $this->actingUserWithRestaurant();
        $table = RestaurantTable::factory()->for($restaurant, 'restaurant')->create();

        Reservation::factory()->count(2)->create([
            'restaurant_id' => $restaurant->id,
            'table_id' => $table->id,
            'date' => '2026-03-24',
            'status' => Reservation::STATUS_ACTIVE,
        ]);
        Reservation::factory()->create([
            'restaurant_id' => $restaurant->id,
            'table_id' => $table->id,
            'date' => '2026-03-10',
            'status' => Reservation::STATUS_ACTIVE,
        ]);
        Reservation::factory()->create([
            'restaurant_id' => $restaurant->id,
            'table_id' => $table->id,
            'date' => '2026-03-24',
            'status' => Reservation::STATUS_CANCELLED,
        ]);
        Reservation::factory()->create([
            'restaurant_id' => $restaurant->id,
            'table_id' => $table->id,
            'date' => '2026-02-20',
            'status' => Reservation::STATUS_ACTIVE,
        ]);

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('reservation_trend', 30)
                ->where('reservation_trend.0.date', '2026-02-23')
                ->where('reservation_trend.29.date', '2026-03-24')
                ->where('reservation_trend', function ($trend): bool {
                    return collect($trend)->contains(
                        fn ($point): bool => data_get($point, 'date') === '2026-03-10'
                            && (int) data_get($point, 'count') === 1,
                    ) && collect($trend)->contains(
                        fn ($point): bool => data_get($point, 'date') === '2026-03-24'
                            && (int) data_get($point, 'count') === 2,
                    );
                }),
            );
    }

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();

        parent::tearDown();
    }

    /**
     * @return array{0: User, 1: Restaurant}
     */
    private function actingUserWithRestaurant(): array
    {
        $user = User::factory()->create();
        $restaurant = Restaurant::factory()->create();
        $user->restaurants()->attach($restaurant);

        return [$user, $restaurant];
    }
}
