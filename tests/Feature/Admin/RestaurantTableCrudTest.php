<?php

namespace Tests\Feature\Admin;

use App\Models\Reservation;
use App\Models\Restaurant;
use App\Models\RestaurantTable;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class RestaurantTableCrudTest extends TestCase
{
    use RefreshDatabase;

    public function test_index_lists_tables_for_current_restaurant(): void
    {
        [$user, $restaurant] = $this->actingUserWithRestaurant();

        RestaurantTable::factory()->for($restaurant, 'restaurant')->create([
            'name' => 'Table A',
        ]);
        RestaurantTable::factory()->for($restaurant, 'restaurant')->create([
            'name' => 'Table B',
        ]);

        $this->actingAs($user)
            ->get(route('admin.tables.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('admin/tables/Index')
                ->where('tables.0.name', 'Table A')
                ->where('tables.1.name', 'Table B'),
            );
    }

    public function test_table_can_be_created(): void
    {
        [$user, $restaurant] = $this->actingUserWithRestaurant();

        $response = $this->actingAs($user)->post(route('admin.tables.store'), [
            'name' => 'Window 4',
            'capacity' => 4,
            'is_active' => '1',
        ]);

        $response->assertRedirect(route('admin.tables.index'));
        $this->assertDatabaseHas('restaurant_tables', [
            'restaurant_id' => $restaurant->id,
            'name' => 'Window 4',
            'capacity' => 4,
            'is_active' => true,
        ]);
    }

    public function test_table_can_be_updated(): void
    {
        [$user, $restaurant] = $this->actingUserWithRestaurant();

        $table = RestaurantTable::factory()->for($restaurant, 'restaurant')->create([
            'name' => 'Original',
            'capacity' => 2,
            'is_active' => true,
        ]);

        $response = $this->actingAs($user)->put(route('admin.tables.update', $table), [
            'name' => 'Patio 6',
            'capacity' => 6,
            'is_active' => '0',
        ]);

        $response->assertRedirect(route('admin.tables.index'));
        $this->assertDatabaseHas('restaurant_tables', [
            'id' => $table->id,
            'name' => 'Patio 6',
            'capacity' => 6,
            'is_active' => false,
        ]);
    }

    public function test_capacity_must_be_greater_than_zero(): void
    {
        [$user] = $this->actingUserWithRestaurant();

        $this->actingAs($user)
            ->from(route('admin.tables.create'))
            ->post(route('admin.tables.store'), [
                'name' => 'Broken table',
                'capacity' => 0,
                'is_active' => '1',
            ])
            ->assertRedirect(route('admin.tables.create'))
            ->assertSessionHasErrors('capacity');
    }

    public function test_table_can_be_deleted_when_it_has_no_reservations(): void
    {
        [$user, $restaurant] = $this->actingUserWithRestaurant();

        $table = RestaurantTable::factory()->for($restaurant, 'restaurant')->create();

        $response = $this->actingAs($user)->delete(route('admin.tables.destroy', $table));

        $response->assertRedirect(route('admin.tables.index'));
        $this->assertDatabaseMissing('restaurant_tables', ['id' => $table->id]);
    }

    public function test_table_with_reservations_cannot_be_deleted(): void
    {
        [$user, $restaurant] = $this->actingUserWithRestaurant();

        $table = RestaurantTable::factory()->for($restaurant, 'restaurant')->create();

        Reservation::query()->create([
            'restaurant_id' => $restaurant->id,
            'table_id' => $table->id,
            'customer_name' => 'Jane Doe',
            'customer_email' => 'jane@example.com',
            'people_count' => 4,
            'date' => '2026-03-15',
            'time' => '19:00:00',
            'token' => 'table-delete-block-token',
            'status' => Reservation::STATUS_ACTIVE,
        ]);

        $response = $this->actingAs($user)->delete(route('admin.tables.destroy', $table));

        $response->assertRedirect(route('admin.tables.index'));
        $this->assertDatabaseHas('restaurant_tables', ['id' => $table->id]);
    }

    public function test_user_cannot_access_table_from_another_restaurant(): void
    {
        [$user] = $this->actingUserWithRestaurant();

        $foreignTable = RestaurantTable::factory()->create();

        $this->actingAs($user)
            ->get(route('admin.tables.edit', $foreignTable))
            ->assertNotFound();

        $this->actingAs($user)
            ->put(route('admin.tables.update', $foreignTable), [
                'name' => 'Hijack',
                'capacity' => 10,
                'is_active' => '1',
            ])
            ->assertNotFound();

        $this->actingAs($user)
            ->delete(route('admin.tables.destroy', $foreignTable))
            ->assertNotFound();
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
