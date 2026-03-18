<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Dish;
use App\Models\Restaurant;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class RestaurantMenuController extends Controller
{
    public function __invoke(string $slug): Response
    {
        $restaurant = Restaurant::query()
            ->where('slug', $slug)
            ->firstOrFail();

        $categories = $restaurant->categories()
            ->whereHas('dishes', fn ($query) => $query
                ->where('restaurant_id', $restaurant->id)
                ->where('is_active', true))
            ->with(['dishes' => fn ($query) => $query
                ->where('restaurant_id', $restaurant->id)
                ->where('is_active', true)
                ->orderBy('name')
                ->orderBy('dishes.id')])
            ->orderBy('position')
            ->orderBy('name')
            ->get();

        return Inertia::render('restaurants/Menu', [
            'restaurant' => $this->serializeRestaurant($restaurant),
            'categories' => $categories
                ->map(fn (Category $category): array => $this->serializeCategory($category))
                ->values()
                ->all(),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeRestaurant(Restaurant $restaurant): array
    {
        return [
            'slug' => $restaurant->slug,
            'name' => $restaurant->name,
            'description' => $restaurant->description,
            'work_hours' => $restaurant->work_hours,
            'open_time' => $this->trimSeconds($restaurant->open_time),
            'close_time' => $this->trimSeconds($restaurant->close_time),
            'logo_url' => $restaurant->logo_path ? Storage::disk('public')->url($restaurant->logo_path) : null,
            'cover_url' => $restaurant->cover_path ? Storage::disk('public')->url($restaurant->cover_path) : null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeCategory(Category $category): array
    {
        return [
            'id' => $category->id,
            'name' => $category->name,
            'description' => $category->description,
            'dishes' => $category->dishes
                ->map(fn (Dish $dish): array => $this->serializeDish($dish))
                ->values()
                ->all(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeDish(Dish $dish): array
    {
        return [
            'id' => $dish->id,
            'name' => $dish->name,
            'description' => $dish->description,
            'weight' => $dish->weight,
            'price' => $dish->price,
            'image_url' => $dish->image_path ? Storage::disk('public')->url($dish->image_path) : null,
        ];
    }

    private function trimSeconds(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        return substr($value, 0, 5);
    }
}
