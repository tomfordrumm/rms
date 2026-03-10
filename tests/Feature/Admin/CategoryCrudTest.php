<?php

namespace Tests\Feature\Admin;

use App\Models\Category;
use App\Models\Dish;
use App\Models\Restaurant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class CategoryCrudTest extends TestCase
{
    use RefreshDatabase;

    public function test_index_lists_categories_sorted_by_position(): void
    {
        [$user, $restaurant] = $this->actingUserWithRestaurant();

        Category::factory()->for($restaurant, 'restaurant')->create([
            'name' => 'Desserts',
            'position' => 3,
        ]);
        Category::factory()->for($restaurant, 'restaurant')->create([
            'name' => 'Starters',
            'position' => 1,
        ]);
        Category::factory()->for($restaurant, 'restaurant')->create([
            'name' => 'Mains',
            'position' => 2,
        ]);

        $this->actingAs($user)
            ->get(route('admin.categories.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('admin/categories/Index')
                ->where('categories.0.name', 'Starters')
                ->where('categories.1.name', 'Mains')
                ->where('categories.2.name', 'Desserts'),
            );
    }

    public function test_category_can_be_created_from_admin_form(): void
    {
        [$user, $restaurant] = $this->actingUserWithRestaurant();

        $response = $this->actingAs($user)->post(route('admin.categories.store'), [
            'name' => 'Seasonal',
            'description' => 'Rotating menu items.',
            'position' => 4,
        ]);

        $response->assertRedirect(route('admin.categories.index'));
        $this->assertDatabaseHas('categories', [
            'restaurant_id' => $restaurant->id,
            'name' => 'Seasonal',
            'position' => 4,
        ]);
    }

    public function test_inline_category_creation_returns_json_and_uses_next_position(): void
    {
        [$user, $restaurant] = $this->actingUserWithRestaurant();

        Category::factory()->for($restaurant, 'restaurant')->create([
            'position' => 7,
        ]);

        $this->actingAs($user)
            ->postJson(route('admin.categories.store'), [
                'name' => 'Chef specials',
                'description' => 'Limited plates.',
            ])
            ->assertCreated()
            ->assertJsonPath('category.name', 'Chef specials')
            ->assertJsonPath('category.position', 8);

        $this->assertDatabaseHas('categories', [
            'restaurant_id' => $restaurant->id,
            'name' => 'Chef specials',
            'position' => 8,
        ]);
    }

    public function test_category_can_be_updated(): void
    {
        [$user, $restaurant] = $this->actingUserWithRestaurant();

        $category = Category::factory()->for($restaurant, 'restaurant')->create([
            'name' => 'Original',
            'position' => 2,
        ]);

        $response = $this->actingAs($user)->put(route('admin.categories.update', $category), [
            'name' => 'Updated',
            'description' => 'Refined description',
            'position' => 5,
        ]);

        $response->assertRedirect(route('admin.categories.index'));
        $this->assertDatabaseHas('categories', [
            'id' => $category->id,
            'name' => 'Updated',
            'position' => 5,
        ]);
    }

    public function test_category_with_dishes_cannot_be_deleted(): void
    {
        [$user, $restaurant] = $this->actingUserWithRestaurant();

        $category = Category::factory()->for($restaurant, 'restaurant')->create();
        $dish = Dish::factory()->for($restaurant, 'restaurant')->create();
        $category->dishes()->attach($dish);

        $response = $this->actingAs($user)->delete(route('admin.categories.destroy', $category));

        $response->assertRedirect(route('admin.categories.index'));
        $this->assertDatabaseHas('categories', ['id' => $category->id]);
    }

    public function test_user_cannot_edit_category_from_another_restaurant(): void
    {
        [$user] = $this->actingUserWithRestaurant();

        $foreignCategory = Category::factory()->create();

        $this->actingAs($user)
            ->get(route('admin.categories.edit', $foreignCategory))
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
