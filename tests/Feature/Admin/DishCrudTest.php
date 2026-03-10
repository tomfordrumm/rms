<?php

namespace Tests\Feature\Admin;

use App\Models\Category;
use App\Models\Dish;
use App\Models\Restaurant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class DishCrudTest extends TestCase
{
    use RefreshDatabase;

    public function test_index_lists_dishes_with_categories(): void
    {
        [$user, $restaurant] = $this->actingUserWithRestaurant();

        $category = Category::factory()->for($restaurant, 'restaurant')->create([
            'name' => 'Starters',
        ]);
        $dish = Dish::factory()->for($restaurant, 'restaurant')->create([
            'name' => 'Smoked carrots',
            'price' => 12.50,
        ]);
        $dish->categories()->attach($category);

        $this->actingAs($user)
            ->get(route('admin.dishes.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('admin/dishes/Index')
                ->where('dishes.0.name', 'Smoked carrots')
                ->where('dishes.0.categories.0.name', 'Starters'),
            );
    }

    public function test_dish_can_be_created_with_categories_and_image(): void
    {
        Storage::fake('public');

        [$user, $restaurant] = $this->actingUserWithRestaurant();
        $categoryA = Category::factory()->for($restaurant, 'restaurant')->create();
        $categoryB = Category::factory()->for($restaurant, 'restaurant')->create();

        $response = $this->actingAs($user)->post(route('admin.dishes.store'), [
            'name' => 'Braised fennel',
            'description' => 'With citrus glaze.',
            'weight' => '220 g',
            'price' => '18.90',
            'image' => UploadedFile::fake()->image('dish.jpg'),
            'is_active' => '1',
            'category_ids' => [$categoryA->id, $categoryB->id],
        ]);

        $response->assertRedirect(route('admin.dishes.index'));

        $dish = Dish::query()->firstOrFail();

        $this->assertSame($restaurant->id, $dish->restaurant_id);
        $this->assertSame(['220 g', '18.90'], [$dish->weight, $dish->price]);
        $this->assertTrue($dish->is_active);
        $this->assertCount(2, $dish->categories);
        Storage::disk('public')->assertExists($dish->image_path);
    }

    public function test_dish_can_be_updated_and_previous_image_is_removed(): void
    {
        Storage::fake('public');

        [$user, $restaurant] = $this->actingUserWithRestaurant();
        $initialCategory = Category::factory()->for($restaurant, 'restaurant')->create();
        $nextCategory = Category::factory()->for($restaurant, 'restaurant')->create();
        $dish = Dish::factory()->for($restaurant, 'restaurant')->create([
            'image_path' => UploadedFile::fake()->image('old.jpg')->store('dishes', 'public'),
            'is_active' => true,
        ]);
        $dish->categories()->attach($initialCategory);
        $oldImagePath = $dish->image_path;

        $response = $this->actingAs($user)->put(route('admin.dishes.update', $dish), [
            'name' => 'Updated dish',
            'description' => 'Now with a new finish.',
            'weight' => '300 g',
            'price' => '24.00',
            'image' => UploadedFile::fake()->image('new.jpg'),
            'is_active' => '0',
            'category_ids' => [$nextCategory->id],
        ]);

        $response->assertRedirect(route('admin.dishes.index'));

        $dish->refresh();

        $this->assertSame('Updated dish', $dish->name);
        $this->assertSame('24.00', $dish->price);
        $this->assertFalse($dish->is_active);
        $this->assertSame([$nextCategory->id], $dish->categories()->pluck('categories.id')->all());
        Storage::disk('public')->assertMissing($oldImagePath);
        Storage::disk('public')->assertExists($dish->image_path);
    }

    public function test_dish_requires_positive_price_weight_and_categories(): void
    {
        [$user] = $this->actingUserWithRestaurant();

        $this->actingAs($user)
            ->from(route('admin.dishes.create'))
            ->post(route('admin.dishes.store'), [
                'name' => 'Invalid',
                'weight' => '',
                'price' => '0',
                'category_ids' => [],
            ])
            ->assertRedirect(route('admin.dishes.create'))
            ->assertSessionHasErrors(['weight', 'price', 'category_ids']);
    }

    public function test_user_cannot_attach_category_from_another_restaurant(): void
    {
        [$user, $restaurant] = $this->actingUserWithRestaurant();
        $foreignRestaurant = Restaurant::factory()->create();
        $foreignCategory = Category::factory()->for($foreignRestaurant, 'restaurant')->create();

        $this->actingAs($user)
            ->from(route('admin.dishes.create'))
            ->post(route('admin.dishes.store'), [
                'name' => 'Dish',
                'weight' => '180 g',
                'price' => '10.00',
                'category_ids' => [$foreignCategory->id],
            ])
            ->assertRedirect(route('admin.dishes.create'))
            ->assertSessionHasErrors('category_ids.0');

        $this->assertSame(0, $restaurant->dishes()->count());
    }

    public function test_user_cannot_edit_dish_from_another_restaurant(): void
    {
        [$user] = $this->actingUserWithRestaurant();
        $foreignDish = Dish::factory()->create();

        $this->actingAs($user)
            ->get(route('admin.dishes.edit', $foreignDish))
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
