<?php

namespace Tests\Feature;

use App\Actions\PublicReservations\BuildReservationAvailabilityAction;
use App\Mail\ReservationConfirmedMail;
use App\Mail\ReservationUpdatedAdminMail;
use App\Mail\ReservationUpdatedGuestMail;
use App\Models\Reservation;
use App\Models\Restaurant;
use App\Models\RestaurantTable;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class PublicRestaurantBookingTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_booking_page_is_accessible_without_authentication(): void
    {
        CarbonImmutable::setTestNow('2026-03-18 11:00:00');

        $restaurant = Restaurant::factory()->create([
            'name' => 'Cinder House',
            'slug' => 'cinder-house',
            'closed_dates' => json_encode(['2026-03-20']),
        ]);
        RestaurantTable::factory()->for($restaurant, 'restaurant')->create([
            'name' => 'Window 2',
            'capacity' => 2,
            'is_active' => true,
        ]);

        $this->get(route('restaurants.booking.show', ['slug' => $restaurant->slug]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('restaurants/Booking')
                ->where('restaurant.name', 'Cinder House')
                ->where('restaurant.slug', 'cinder-house')
                ->where('restaurant.closed_dates.0', '2026-03-20')
                ->where('initialAvailability.booking_rules.slot_duration_minutes', 60)
                ->where('initialState.people_count', 2),
            );
    }

    public function test_public_booking_page_returns_not_found_for_unknown_slug(): void
    {
        $this->get('/r/unknown/booking')->assertNotFound();
    }

    public function test_availability_marks_closed_dates_and_filters_slots_and_tables(): void
    {
        CarbonImmutable::setTestNow('2026-03-18 11:15:00');

        $restaurant = Restaurant::factory()->create([
            'slug' => 'atelier',
            'open_time' => '12:00:00',
            'close_time' => '15:00:00',
            'closed_dates' => json_encode(['2026-03-20']),
        ]);
        $activeSmall = RestaurantTable::factory()->for($restaurant, 'restaurant')->create([
            'name' => 'Counter',
            'capacity' => 2,
            'is_active' => true,
        ]);
        $activeLarge = RestaurantTable::factory()->for($restaurant, 'restaurant')->create([
            'name' => 'Garden',
            'capacity' => 4,
            'is_active' => true,
        ]);
        RestaurantTable::factory()->for($restaurant, 'restaurant')->create([
            'name' => 'Hidden',
            'capacity' => 8,
            'is_active' => false,
        ]);

        Reservation::factory()->create([
            'restaurant_id' => $restaurant->id,
            'table_id' => $activeLarge->id,
            'date' => '2026-03-19',
            'time' => '12:00:00',
            'status' => Reservation::STATUS_ACTIVE,
        ]);
        Reservation::factory()->create([
            'restaurant_id' => $restaurant->id,
            'table_id' => $activeSmall->id,
            'date' => '2026-03-19',
            'time' => '13:00:00',
            'status' => Reservation::STATUS_CANCELLED,
        ]);

        $response = $this->getJson(route('restaurants.booking.availability', [
            'slug' => $restaurant->slug,
            'people_count' => 3,
            'date' => '2026-03-19',
            'time' => '13:00',
        ]));

        $response->assertOk()
            ->assertJsonPath('closed_dates.0', '2026-03-20')
            ->assertJsonPath('booking_rules.max_party_size', 4)
            ->assertJsonPath('selected_date_slots.0.value', '13:00')
            ->assertJsonPath('selected_date_slots.1.value', '14:00')
            ->assertJsonPath('selected_time_tables.0.name', 'Garden');

        $slotValues = collect($response->json('selected_date_slots'))->pluck('value')->all();
        $tableNames = collect($response->json('selected_time_tables'))->pluck('name')->all();

        $this->assertSame(['13:00', '14:00'], $slotValues);
        $this->assertSame(['Garden'], $tableNames);
        $this->assertContains('2026-03-19', $response->json('bookable_dates'));
        $this->assertNotContains('2026-03-20', $response->json('bookable_dates'));
    }

    public function test_guest_can_create_reservation_and_receive_confirmation_email(): void
    {
        CarbonImmutable::setTestNow('2026-03-18 10:00:00');
        Mail::fake();

        $restaurant = Restaurant::factory()->create([
            'name' => 'North Table',
            'slug' => 'north-table',
        ]);
        $table = RestaurantTable::factory()->for($restaurant, 'restaurant')->create([
            'name' => 'Window',
            'capacity' => 4,
            'is_active' => true,
        ]);

        $response = $this->post(route('restaurants.booking.store', ['slug' => $restaurant->slug]), [
            'customer_name' => 'Alex Morgan',
            'customer_email' => 'alex@example.com',
            'people_count' => 2,
            'date' => '2026-03-19',
            'time' => '12:00',
            'table_id' => $table->id,
        ]);

        $reservation = Reservation::query()->firstOrFail();

        $response->assertRedirect(route('restaurants.booking.success', [
            'slug' => $restaurant->slug,
            'token' => $reservation->token,
        ]));

        $reservation->refresh();

        $this->assertSame('2026-03-19', $reservation->date?->format('Y-m-d'));
        $this->assertStringStartsWith('12:00', (string) $reservation->time);
        $this->assertSame(Reservation::STATUS_ACTIVE, $reservation->status);
        $this->assertNotSame('', $reservation->token);

        Mail::assertSent(ReservationConfirmedMail::class, function (ReservationConfirmedMail $mail) use ($reservation): bool {
            return $mail->hasTo('alex@example.com')
                && str_contains($mail->manageUrl, $reservation->token);
        });
    }

    public function test_success_page_loads_by_valid_slug_and_token(): void
    {
        $reservation = $this->reservationWithRestaurant();

        $this->get(route('restaurants.booking.success', [
            'slug' => $reservation->restaurant->slug,
            'token' => $reservation->token,
        ]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('restaurants/BookingSuccess')
                ->where('reservation.customer_email', $reservation->customer_email)
                ->where('reservation.table.name', $reservation->restaurantTable->name),
            );
    }

    public function test_management_page_loads_and_can_cancel_reservation(): void
    {
        $reservation = $this->reservationWithRestaurant();

        $this->get(route('restaurants.booking.manage', [
            'slug' => $reservation->restaurant->slug,
            'token' => $reservation->token,
        ]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('restaurants/BookingManage')
                ->where('reservation.status', Reservation::STATUS_ACTIVE),
            );

        $this->patch(route('restaurants.booking.cancel', [
            'slug' => $reservation->restaurant->slug,
            'token' => $reservation->token,
        ]))
            ->assertRedirect(route('restaurants.booking.manage', [
                'slug' => $reservation->restaurant->slug,
                'token' => $reservation->token,
            ]));

        $this->assertDatabaseHas('reservations', [
            'id' => $reservation->id,
            'status' => Reservation::STATUS_CANCELLED,
        ]);
    }

    public function test_management_page_can_update_reservation_and_send_guest_and_admin_emails(): void
    {
        CarbonImmutable::setTestNow('2026-03-18 10:00:00');
        Mail::fake();

        $reservation = $this->reservationWithRestaurant();
        $restaurant = $reservation->restaurant;
        $admin = User::factory()->create(['email' => 'owner@example.com']);
        $admin->restaurants()->attach($restaurant);
        $newTable = RestaurantTable::factory()->for($restaurant, 'restaurant')->create([
            'name' => 'Garden 6',
            'capacity' => 6,
            'is_active' => true,
        ]);

        $response = $this->patch(route('restaurants.booking.update', [
            'slug' => $restaurant->slug,
            'token' => $reservation->token,
        ]), [
            'people_count' => 5,
            'date' => '2026-03-20',
            'time' => '19:00',
            'table_id' => $newTable->id,
        ]);

        $response->assertRedirect(route('restaurants.booking.manage', [
            'slug' => $restaurant->slug,
            'token' => $reservation->token,
        ]));

        $reservation->refresh();

        $this->assertSame('2026-03-20', $reservation->date?->format('Y-m-d'));
        $this->assertStringStartsWith('19:00', (string) $reservation->time);
        $this->assertSame(5, $reservation->people_count);
        $this->assertSame($newTable->id, $reservation->table_id);

        Mail::assertSent(ReservationUpdatedGuestMail::class, function (ReservationUpdatedGuestMail $mail) use ($reservation): bool {
            return $mail->hasTo('jamie@example.com')
                && $mail->previousReservation['date'] === '2026-03-19'
                && str_contains($mail->manageUrl, $reservation->token);
        });

        Mail::assertSent(ReservationUpdatedAdminMail::class, function (ReservationUpdatedAdminMail $mail) use ($reservation): bool {
            return $mail->hasTo('owner@example.com')
                && $mail->reservation->id === $reservation->id;
        });
    }

    public function test_management_update_rejects_unavailable_slot_or_table(): void
    {
        CarbonImmutable::setTestNow('2026-03-18 10:00:00');

        $reservation = $this->reservationWithRestaurant();
        $restaurant = $reservation->restaurant;
        $competingTable = RestaurantTable::factory()->for($restaurant, 'restaurant')->create([
            'name' => 'Patio 4',
            'capacity' => 4,
            'is_active' => true,
        ]);

        Reservation::factory()->create([
            'restaurant_id' => $restaurant->id,
            'table_id' => $competingTable->id,
            'date' => '2026-03-19',
            'time' => '19:00:00',
            'status' => Reservation::STATUS_ACTIVE,
        ]);

        $this->from(route('restaurants.booking.manage', [
            'slug' => $restaurant->slug,
            'token' => $reservation->token,
        ]))
            ->patch(route('restaurants.booking.update', [
                'slug' => $restaurant->slug,
                'token' => $reservation->token,
            ]), [
                'people_count' => 4,
                'date' => '2026-03-19',
                'time' => '19:00',
                'table_id' => $competingTable->id,
            ])
            ->assertRedirect(route('restaurants.booking.manage', [
                'slug' => $restaurant->slug,
                'token' => $reservation->token,
            ]))
            ->assertSessionHasErrors('table_id');
    }

    public function test_manage_availability_excludes_current_reservation_from_conflicts(): void
    {
        CarbonImmutable::setTestNow('2026-03-18 10:00:00');

        $reservation = $this->reservationWithRestaurant();

        $response = $this->getJson(route('restaurants.booking.availability', [
            'slug' => $reservation->restaurant->slug,
            'token' => $reservation->token,
            'people_count' => $reservation->people_count,
            'date' => $reservation->date?->format('Y-m-d'),
            'time' => '18:00',
        ]));

        $response->assertOk()
            ->assertJsonPath('selected_time_tables.0.id', $reservation->table_id);
    }

    public function test_invalid_token_or_mismatched_slug_returns_not_found(): void
    {
        $reservation = $this->reservationWithRestaurant();
        $otherRestaurant = Restaurant::factory()->create(['slug' => 'other-room']);

        $this->get(route('restaurants.booking.manage', [
            'slug' => $otherRestaurant->slug,
            'token' => $reservation->token,
        ]))->assertNotFound();

        $this->get(route('restaurants.booking.success', [
            'slug' => $reservation->restaurant->slug,
            'token' => 'missing-token',
        ]))->assertNotFound();
    }

    public function test_cancelled_reservations_do_not_block_future_availability(): void
    {
        CarbonImmutable::setTestNow('2026-03-18 10:00:00');

        $restaurant = Restaurant::factory()->create(['slug' => 'luna']);
        $table = RestaurantTable::factory()->for($restaurant, 'restaurant')->create([
            'capacity' => 4,
            'is_active' => true,
        ]);

        Reservation::factory()->create([
            'restaurant_id' => $restaurant->id,
            'table_id' => $table->id,
            'date' => '2026-03-19',
            'time' => '12:00:00',
            'status' => Reservation::STATUS_CANCELLED,
        ]);

        $response = $this->getJson(route('restaurants.booking.availability', [
            'slug' => $restaurant->slug,
            'people_count' => 2,
            'date' => '2026-03-19',
            'time' => '12:00',
        ]));

        $response->assertOk();
        $this->assertCount(1, $response->json('selected_time_tables'));
    }

    public function test_booking_request_rechecks_conflict_inside_creation_flow(): void
    {
        CarbonImmutable::setTestNow('2026-03-18 10:00:00');

        $restaurant = Restaurant::factory()->create(['slug' => 'race-room']);
        $table = RestaurantTable::factory()->for($restaurant, 'restaurant')->create([
            'capacity' => 4,
            'is_active' => true,
        ]);

        $this->instance(BuildReservationAvailabilityAction::class, new class($table->id, $table->name) extends BuildReservationAvailabilityAction
        {
            public function __construct(
                private readonly int $tableId,
                private readonly string $tableName,
            ) {}

            public function handle(
                Restaurant $restaurant,
                int $peopleCount = 2,
                ?string $selectedDate = null,
                ?string $selectedTime = null,
                ?int $excludeReservationId = null,
            ): array {
                return [
                    'calendar_dates' => ['2026-03-19'],
                    'bookable_dates' => ['2026-03-19'],
                    'closed_dates' => [],
                    'selected_date_slots' => [
                        ['value' => '12:00', 'label' => '12:00 PM', 'available_tables_count' => 1],
                    ],
                    'selected_time_tables' => [
                        ['id' => $this->tableId, 'name' => $this->tableName, 'capacity' => 4],
                    ],
                    'booking_rules' => [
                        'slot_duration_minutes' => 60,
                        'min_party_size' => 1,
                        'max_party_size' => 4,
                        'timezone_note' => 'UTC',
                    ],
                    'booking_enabled' => true,
                    'booking_notice' => null,
                ];
            }
        });

        Reservation::factory()->create([
            'restaurant_id' => $restaurant->id,
            'table_id' => $table->id,
            'date' => '2026-03-19',
            'time' => '12:00:00',
            'status' => Reservation::STATUS_ACTIVE,
        ]);

        $this->from(route('restaurants.booking.show', ['slug' => $restaurant->slug]))
            ->post(route('restaurants.booking.store', ['slug' => $restaurant->slug]), [
                'customer_name' => 'Alex Morgan',
                'customer_email' => 'alex@example.com',
                'people_count' => 2,
                'date' => '2026-03-19',
                'time' => '12:00',
                'table_id' => $table->id,
            ])
            ->assertRedirect(route('restaurants.booking.show', ['slug' => $restaurant->slug]))
            ->assertSessionHasErrors('table_id');
    }

    private function reservationWithRestaurant(): Reservation
    {
        $restaurant = Restaurant::factory()->create(['slug' => 'harbor-room']);
        $owner = User::factory()->create(['email' => 'primary@example.com']);
        $owner->restaurants()->attach($restaurant);
        $table = RestaurantTable::factory()->for($restaurant, 'restaurant')->create([
            'name' => 'Booth 4',
            'capacity' => 4,
            'is_active' => true,
        ]);

        return Reservation::factory()->create([
            'restaurant_id' => $restaurant->id,
            'table_id' => $table->id,
            'customer_name' => 'Jamie Doe',
            'customer_email' => 'jamie@example.com',
            'people_count' => 4,
            'date' => '2026-03-19',
            'time' => '18:00:00',
            'token' => 'reservation-token-public',
            'status' => Reservation::STATUS_ACTIVE,
        ]);
    }
}
