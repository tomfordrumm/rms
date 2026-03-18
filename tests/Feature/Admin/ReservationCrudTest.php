<?php

namespace Tests\Feature\Admin;

use App\Models\Reservation;
use App\Models\Restaurant;
use App\Models\RestaurantTable;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ReservationCrudTest extends TestCase
{
    use RefreshDatabase;

    public function test_index_lists_restaurant_reservations(): void
    {
        [$user, $restaurant] = $this->actingUserWithRestaurant();
        $table = RestaurantTable::factory()->for($restaurant, 'restaurant')->create(['name' => 'Window']);

        Reservation::factory()->create([
            'restaurant_id' => $restaurant->id,
            'table_id' => $table->id,
            'customer_name' => 'John Doe',
            'customer_email' => 'john@example.com',
        ]);

        $this->actingAs($user)
            ->get(route('admin.reservations.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('admin/reservations/Index')
                ->where('reservations.0.customer_email', 'john@example.com')
                ->where('reservations.0.table.name', 'Window'),
            );
    }

    public function test_index_filters_by_date_email_and_status(): void
    {
        [$user, $restaurant] = $this->actingUserWithRestaurant();
        $table = RestaurantTable::factory()->for($restaurant, 'restaurant')->create();

        Reservation::factory()->create([
            'restaurant_id' => $restaurant->id,
            'table_id' => $table->id,
            'customer_email' => 'first@example.com',
            'date' => '2026-03-20',
            'status' => Reservation::STATUS_ACTIVE,
        ]);

        Reservation::factory()->create([
            'restaurant_id' => $restaurant->id,
            'table_id' => $table->id,
            'customer_email' => 'second@example.com',
            'date' => '2026-03-21',
            'status' => Reservation::STATUS_CANCELLED,
        ]);

        $this->actingAs($user)
            ->get(route('admin.reservations.index', [
                'date' => '2026-03-21',
                'email' => 'second@',
                'status' => Reservation::STATUS_CANCELLED,
            ]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('reservations', 1)
                ->where('reservations.0.customer_email', 'second@example.com')
                ->where('filters.status', Reservation::STATUS_CANCELLED),
            );
    }

    public function test_reservation_can_be_updated(): void
    {
        [$user, $restaurant] = $this->actingUserWithRestaurant();
        $oldTable = RestaurantTable::factory()->for($restaurant, 'restaurant')->create(['capacity' => 2]);
        $newTable = RestaurantTable::factory()->for($restaurant, 'restaurant')->create(['capacity' => 6]);
        $reservation = Reservation::factory()->create([
            'restaurant_id' => $restaurant->id,
            'table_id' => $oldTable->id,
            'people_count' => 2,
            'date' => '2026-03-20',
            'time' => '18:00:00',
        ]);

        $response = $this->actingAs($user)->put(route('admin.reservations.update', $reservation), [
            'date' => '2026-03-22',
            'time' => '19:30',
            'table_id' => $newTable->id,
            'people_count' => 5,
        ]);

        $response->assertRedirect(route('admin.reservations.index'));
        $reservation->refresh();

        $this->assertSame('2026-03-22', $reservation->date?->format('Y-m-d'));
        $this->assertStringStartsWith('19:30', (string) $reservation->time);
        $this->assertSame($newTable->id, $reservation->table_id);
        $this->assertSame(5, $reservation->people_count);
    }

    public function test_update_requires_table_capacity(): void
    {
        [$user, $restaurant] = $this->actingUserWithRestaurant();
        $table = RestaurantTable::factory()->for($restaurant, 'restaurant')->create(['capacity' => 2]);
        $reservation = Reservation::factory()->create([
            'restaurant_id' => $restaurant->id,
            'table_id' => $table->id,
        ]);

        $this->actingAs($user)
            ->from(route('admin.reservations.edit', $reservation))
            ->put(route('admin.reservations.update', $reservation), [
                'date' => '2026-03-20',
                'time' => '18:00',
                'table_id' => $table->id,
                'people_count' => 4,
            ])
            ->assertRedirect(route('admin.reservations.edit', $reservation))
            ->assertSessionHasErrors('people_count');
    }

    public function test_update_requires_available_time_slot(): void
    {
        [$user, $restaurant] = $this->actingUserWithRestaurant();
        $table = RestaurantTable::factory()->for($restaurant, 'restaurant')->create(['capacity' => 6]);
        Reservation::factory()->create([
            'restaurant_id' => $restaurant->id,
            'table_id' => $table->id,
            'date' => '2026-03-20',
            'time' => '18:00:00',
            'status' => Reservation::STATUS_ACTIVE,
        ]);
        $reservation = Reservation::factory()->create([
            'restaurant_id' => $restaurant->id,
            'table_id' => $table->id,
            'date' => '2026-03-20',
            'time' => '19:00:00',
        ]);

        $this->actingAs($user)
            ->from(route('admin.reservations.edit', $reservation))
            ->put(route('admin.reservations.update', $reservation), [
                'date' => '2026-03-20',
                'time' => '18:00',
                'table_id' => $table->id,
                'people_count' => 2,
            ])
            ->assertRedirect(route('admin.reservations.edit', $reservation))
            ->assertSessionHasErrors('time');
    }

    public function test_reservation_can_be_cancelled(): void
    {
        [$user, $restaurant] = $this->actingUserWithRestaurant();
        $table = RestaurantTable::factory()->for($restaurant, 'restaurant')->create();
        $reservation = Reservation::factory()->create([
            'restaurant_id' => $restaurant->id,
            'table_id' => $table->id,
            'status' => Reservation::STATUS_ACTIVE,
        ]);

        $response = $this->actingAs($user)->patch(route('admin.reservations.cancel', $reservation));

        $response->assertRedirect(route('admin.reservations.index'));
        $this->assertDatabaseHas('reservations', [
            'id' => $reservation->id,
            'status' => Reservation::STATUS_CANCELLED,
        ]);
    }

    public function test_user_cannot_access_reservation_from_another_restaurant(): void
    {
        [$user] = $this->actingUserWithRestaurant();
        $foreignReservation = Reservation::factory()->create();

        $this->actingAs($user)
            ->get(route('admin.reservations.edit', $foreignReservation))
            ->assertNotFound();

        $this->actingAs($user)
            ->put(route('admin.reservations.update', $foreignReservation), [
                'date' => '2026-03-20',
                'time' => '18:00',
                'table_id' => $foreignReservation->table_id,
                'people_count' => 2,
            ])
            ->assertNotFound();

        $this->actingAs($user)
            ->patch(route('admin.reservations.cancel', $foreignReservation))
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
