<?php

namespace Tests\Feature\Console;

use App\Models\Category;
use App\Models\Dish;
use App\Models\Restaurant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GenerateRestaurantMenuCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_generates_a_realistic_menu_for_a_restaurant_by_id(): void
    {
        $restaurant = Restaurant::factory()->create([
            'name' => 'Bistro Nova',
        ]);

        $this->artisan('dishes:generate', [
            'restaurant' => $restaurant->id,
        ])
            ->expectsOutput('Generated 8 categories and 29 dishes for restaurant "Bistro Nova" (ID '.$restaurant->id.').')
            ->assertSuccessful();

        $this->assertSame(8, Category::query()->where('restaurant_id', $restaurant->id)->count());
        $this->assertSame(29, Dish::query()->where('restaurant_id', $restaurant->id)->count());

        $category = Category::query()
            ->where('restaurant_id', $restaurant->id)
            ->where('name', 'Основные блюда')
            ->first();

        $this->assertNotNull($category);
        $this->assertSame(5, $category->position);

        $dish = Dish::query()
            ->where('restaurant_id', $restaurant->id)
            ->where('name', 'Филе дорадо с овощами')
            ->first();

        $this->assertNotNull($dish);
        $this->assertSame('18.90', $dish->price);
        $this->assertDatabaseHas('category_dish', [
            'category_id' => $category->id,
            'dish_id' => $dish->id,
        ]);
    }

    public function test_it_is_idempotent_when_run_multiple_times(): void
    {
        $restaurant = Restaurant::factory()->create([
            'slug' => 'menu-safe',
        ]);

        $this->artisan('dishes:generate', [
            'restaurant' => 'menu-safe',
        ])->assertSuccessful();

        $this->artisan('dishes:generate', [
            'restaurant' => 'menu-safe',
        ])->assertSuccessful();

        $this->assertSame(8, Category::query()->where('restaurant_id', $restaurant->id)->count());
        $this->assertSame(29, Dish::query()->where('restaurant_id', $restaurant->id)->count());
        $this->assertSame(29, \DB::table('category_dish')->count());
    }

    public function test_it_fails_for_unknown_restaurant(): void
    {
        $this->artisan('dishes:generate', [
            'restaurant' => 999999,
        ])
            ->expectsOutput('Restaurant not found.')
            ->assertFailed();
    }
}
