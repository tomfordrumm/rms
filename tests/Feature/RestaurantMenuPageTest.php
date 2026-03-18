<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Dish;
use App\Models\Restaurant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class RestaurantMenuPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_menu_page_is_accessible_without_authentication(): void
    {
        $restaurant = Restaurant::factory()->create([
            'name' => 'House of Ember',
            'slug' => 'house-of-ember',
            'description' => 'Seasonal fire-led dining.',
            'work_hours' => 'Daily from noon until late',
            'open_time' => '12:00:00',
            'close_time' => '23:00:00',
        ]);

        $category = Category::factory()->for($restaurant, 'restaurant')->create([
            'name' => 'Main Courses',
            'position' => 2,
        ]);
        $dish = Dish::factory()->for($restaurant, 'restaurant')->create([
            'name' => 'Coal roasted seabass',
            'price' => 34.00,
            'weight' => '340 g',
            'is_active' => true,
        ]);
        $dish->categories()->attach($category);

        $this->get(route('restaurants.menu', ['slug' => $restaurant->slug]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('restaurants/Menu')
                ->where('restaurant.name', 'House of Ember')
                ->where('restaurant.work_hours', 'Daily from noon until late')
                ->where('restaurant.open_time', '12:00')
                ->where('restaurant.close_time', '23:00')
                ->where('categories.0.name', 'Main Courses')
                ->where('categories.0.dishes.0.name', 'Coal roasted seabass')
                ->where('categories.0.dishes.0.weight', '340 g')
                ->where('categories.0.dishes.0.price', '34.00'),
            );
    }

    public function test_menu_page_returns_not_found_for_unknown_slug(): void
    {
        $this->get('/r/missing-restaurant/menu')->assertNotFound();
    }

    public function test_menu_page_shows_only_active_dishes_and_non_empty_categories(): void
    {
        $restaurant = Restaurant::factory()->create([
            'slug' => 'ember-room',
        ]);

        $starters = Category::factory()->for($restaurant, 'restaurant')->create([
            'name' => 'Starters',
            'position' => 1,
        ]);
        $desserts = Category::factory()->for($restaurant, 'restaurant')->create([
            'name' => 'Desserts',
            'position' => 2,
        ]);

        $activeDish = Dish::factory()->for($restaurant, 'restaurant')->create([
            'name' => 'Charred leeks',
            'is_active' => true,
        ]);
        $inactiveDish = Dish::factory()->for($restaurant, 'restaurant')->create([
            'name' => 'Hidden special',
            'is_active' => false,
        ]);

        $activeDish->categories()->attach($starters);
        $inactiveDish->categories()->attach([$starters->id, $desserts->id]);

        $this->get(route('restaurants.menu', ['slug' => $restaurant->slug]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('categories', 1)
                ->where('categories.0.name', 'Starters')
                ->where('categories.0.dishes', function ($dishes): bool {
                    $values = collect($dishes)->values();

                    return $values->count() === 1
                        && $values->first()['name'] === 'Charred leeks';
                }),
            );
    }

    public function test_menu_page_includes_public_media_urls_when_present(): void
    {
        Storage::fake('public');

        $restaurant = Restaurant::factory()->create([
            'slug' => 'atelier-dining',
            'logo_path' => 'restaurants/logos/logo.png',
            'cover_path' => 'restaurants/covers/cover.png',
        ]);

        $category = Category::factory()->for($restaurant, 'restaurant')->create([
            'position' => 1,
        ]);
        $dish = Dish::factory()->for($restaurant, 'restaurant')->create([
            'image_path' => 'dishes/dish.png',
            'is_active' => true,
        ]);
        $dish->categories()->attach($category);

        $this->get(route('restaurants.menu', ['slug' => $restaurant->slug]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('restaurant.logo_url', Storage::disk('public')->url('restaurants/logos/logo.png'))
                ->where('restaurant.cover_url', Storage::disk('public')->url('restaurants/covers/cover.png'))
                ->where('categories.0.dishes.0.image_url', Storage::disk('public')->url('dishes/dish.png')),
            );
    }
}
