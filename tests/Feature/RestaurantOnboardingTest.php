<?php

namespace Tests\Feature;

use App\Models\Restaurant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class RestaurantOnboardingTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_redirects_user_without_restaurant_to_restaurant_settings(): void
    {
        $response = $this->post(route('register.store'), [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $this->assertAuthenticated();
        $response->assertRedirect(route('restaurant.edit', absolute: false));
    }

    public function test_login_redirects_user_without_restaurant_to_restaurant_settings(): void
    {
        $user = User::factory()->create();

        $response = $this->post(route('login.store'), [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $response->assertRedirect(route('restaurant.edit', absolute: false));
    }

    public function test_user_without_restaurant_is_redirected_from_dashboard_but_can_open_profile(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertRedirect(route('restaurant.edit'));

        $this->actingAs($user)
            ->get(route('profile.edit'))
            ->assertOk();
    }

    public function test_user_with_restaurant_can_open_dashboard(): void
    {
        $user = User::factory()->create();
        $restaurant = Restaurant::factory()->create();
        $user->restaurants()->attach($restaurant);

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk();
    }

    public function test_restaurant_settings_page_renders_onboarding_state_when_restaurant_is_missing(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('restaurant.edit'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('settings/Restaurant')
                ->where('isOnboarding', true)
                ->where('restaurant', null),
            );
    }

    public function test_restaurant_can_be_created_and_attached_to_current_user(): void
    {
        Storage::fake('public');

        $user = User::factory()->create();

        $response = $this->actingAs($user)->post(route('restaurant.store'), [
            'name' => 'Casa Atlantica',
            'description' => 'Seafood and wood fire.',
            'slug' => '',
            'contacts' => "hello@casa.test\n+351 000 000 000",
            'work_hours' => 'Mon-Sun 12:00-23:00',
            'open_time' => '12:00',
            'close_time' => '23:00',
            'closed_dates' => ['2026-12-24', '2026-12-25'],
            'logo' => UploadedFile::fake()->image('logo.png'),
            'cover' => UploadedFile::fake()->image('cover.jpg', 1600, 900),
        ]);

        $restaurant = $user->fresh()->primaryRestaurant();

        $response->assertRedirect(route('restaurant.edit'));
        $this->assertNotNull($restaurant);
        $this->assertSame('casa-atlantica', $restaurant->slug);
        $this->assertSame(['2026-12-24', '2026-12-25'], json_decode($restaurant->closed_dates, true));
        $this->assertDatabaseHas('restaurant_user', [
            'user_id' => $user->id,
            'restaurant_id' => $restaurant->id,
        ]);
        Storage::disk('public')->assertExists($restaurant->logo_path);
        Storage::disk('public')->assertExists($restaurant->cover_path);
    }

    public function test_existing_restaurant_is_updated_instead_of_creating_a_second_record(): void
    {
        Storage::fake('public');

        $user = User::factory()->create();
        $restaurant = Restaurant::factory()->create([
            'name' => 'Original Name',
            'slug' => 'original-name',
            'logo_path' => null,
            'cover_path' => null,
            'closed_dates' => json_encode(['2026-01-01']),
        ]);
        $user->restaurants()->attach($restaurant);

        $response = $this->actingAs($user)->patch(route('restaurant.update'), [
            'name' => 'Updated Name',
            'description' => 'Updated description',
            'slug' => '',
            'contacts' => 'New contact block',
            'work_hours' => 'Tue-Sun 13:00-22:00',
            'open_time' => '13:00',
            'close_time' => '22:00',
            'closed_dates' => ['2026-02-01', '2026-02-02'],
            'logo' => UploadedFile::fake()->image('new-logo.webp'),
        ]);

        $restaurant->refresh();

        $response->assertRedirect(route('restaurant.edit'));
        $this->assertSame(1, Restaurant::count());
        $this->assertSame('Updated Name', $restaurant->name);
        $this->assertSame('updated-name', $restaurant->slug);
        $this->assertSame(['2026-02-01', '2026-02-02'], json_decode($restaurant->closed_dates, true));
        Storage::disk('public')->assertExists($restaurant->logo_path);
    }

    public function test_restaurant_settings_page_renders_edit_state_when_restaurant_exists(): void
    {
        $user = User::factory()->create();
        $restaurant = Restaurant::factory()->create([
            'name' => 'Already Open',
            'slug' => 'already-open',
        ]);
        $user->restaurants()->attach($restaurant);

        $this->actingAs($user)
            ->get(route('restaurant.edit'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('settings/Restaurant')
                ->where('isOnboarding', false)
                ->where('restaurant.name', 'Already Open')
                ->where('restaurant.slug', 'already-open'),
            );
    }

    public function test_name_is_required(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->from(route('restaurant.edit'))
            ->post(route('restaurant.store'), [
                'name' => '',
                'open_time' => '12:00',
                'close_time' => '23:00',
            ])
            ->assertRedirect(route('restaurant.edit'))
            ->assertSessionHasErrors('name');
    }

    public function test_slug_must_be_unique(): void
    {
        $user = User::factory()->create();
        Restaurant::factory()->create([
            'name' => 'Occupied Slug',
            'slug' => 'occupied-slug',
        ]);

        $this->actingAs($user)
            ->from(route('restaurant.edit'))
            ->post(route('restaurant.store'), [
                'name' => 'Second Restaurant',
                'slug' => 'occupied slug',
                'open_time' => '12:00',
                'close_time' => '23:00',
            ])
            ->assertRedirect(route('restaurant.edit'))
            ->assertSessionHasErrors('slug');
    }

    public function test_closing_time_must_be_later_than_opening_time(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->from(route('restaurant.edit'))
            ->post(route('restaurant.store'), [
                'name' => 'Late Kitchen',
                'open_time' => '18:00',
                'close_time' => '17:00',
            ])
            ->assertRedirect(route('restaurant.edit'))
            ->assertSessionHasErrors('close_time');
    }

    public function test_closed_dates_must_be_unique(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->from(route('restaurant.edit'))
            ->post(route('restaurant.store'), [
                'name' => 'Date Test',
                'open_time' => '12:00',
                'close_time' => '22:00',
                'closed_dates' => ['2026-05-01', '2026-05-01'],
            ])
            ->assertRedirect(route('restaurant.edit'))
            ->assertSessionHasErrors('closed_dates');
    }

    public function test_logo_and_cover_must_be_images(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->from(route('restaurant.edit'))
            ->post(route('restaurant.store'), [
                'name' => 'File Test',
                'open_time' => '12:00',
                'close_time' => '22:00',
                'logo' => UploadedFile::fake()->create('logo.pdf', 100, 'application/pdf'),
                'cover' => UploadedFile::fake()->create('cover.txt', 10, 'text/plain'),
            ])
            ->assertRedirect(route('restaurant.edit'))
            ->assertSessionHasErrors(['logo', 'cover']);
    }
}
