<?php

namespace Tests\Feature\Admin;

use App\Models\Restaurant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class QrPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_redirected_to_login(): void
    {
        $this->get(route('admin.qr.index'))
            ->assertRedirect(route('login'));
    }

    public function test_user_without_restaurant_is_redirected_to_restaurant_settings(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('admin.qr.index'))
            ->assertRedirect(route('restaurant.edit'));
    }

    public function test_qr_page_renders_menu_and_booking_qr_codes(): void
    {
        [$user, $restaurant] = $this->actingUserWithRestaurant();

        $this->actingAs($user)
            ->get(route('admin.qr.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('admin/qr/Index')
                ->where('restaurant_name', $restaurant->name)
                ->where('slug', $restaurant->slug)
                ->where('menu_url', route('restaurants.menu', ['slug' => $restaurant->slug]))
                ->where('booking_url', route('restaurants.booking.show', ['slug' => $restaurant->slug]))
                ->where('menu_qr_svg', fn (string $svg): bool => str_contains($svg, '<svg'))
                ->where('booking_qr_svg', fn (string $svg): bool => str_contains($svg, '<svg')),
            );
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
