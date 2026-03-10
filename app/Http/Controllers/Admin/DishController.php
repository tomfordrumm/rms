<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\DishUpsertRequest;
use App\Models\Category;
use App\Models\Dish;
use App\Models\Restaurant;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class DishController extends Controller
{
    public function index(Request $request): Response
    {
        $restaurant = $this->restaurantFromRequest($request);

        return Inertia::render('admin/dishes/Index', [
            'dishes' => $restaurant->dishes()
                ->with(['categories:id,name'])
                ->orderBy('name')
                ->get()
                ->map(fn (Dish $dish): array => $this->serializeDishForList($dish)),
            'status' => $request->session()->get('status'),
        ]);
    }

    public function create(Request $request): Response
    {
        return Inertia::render('admin/dishes/Create', [
            'dish' => null,
            'categories' => $this->categoryOptions($request),
        ]);
    }

    public function store(DishUpsertRequest $request): RedirectResponse
    {
        $restaurant = $this->restaurantFromRequest($request);
        $validated = $request->validated();

        $dish = $restaurant->dishes()->create([
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'weight' => $validated['weight'],
            'price' => $validated['price'],
            'is_active' => $validated['is_active'] ?? false,
            'image_path' => $request->hasFile('image')
                ? $request->file('image')->store('dishes', 'public')
                : null,
        ]);

        $dish->categories()->sync($validated['category_ids']);

        return to_route('admin.dishes.index')->with('status', 'dish-created');
    }

    public function edit(Request $request, int $dish): Response
    {
        $dishModel = $this->findDish($request, $dish);

        return Inertia::render('admin/dishes/Edit', [
            'dish' => $this->serializeDishForForm($dishModel),
            'categories' => $this->categoryOptions($request),
        ]);
    }

    public function update(DishUpsertRequest $request, int $dish): RedirectResponse
    {
        $dishModel = $this->findDish($request, $dish);
        $validated = $request->validated();

        $payload = [
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'weight' => $validated['weight'],
            'price' => $validated['price'],
            'is_active' => $validated['is_active'] ?? false,
        ];

        if ($request->hasFile('image')) {
            if ($dishModel->image_path !== null) {
                Storage::disk('public')->delete($dishModel->image_path);
            }

            $payload['image_path'] = $request->file('image')->store('dishes', 'public');
        }

        $dishModel->update($payload);
        $dishModel->categories()->sync($validated['category_ids']);

        return to_route('admin.dishes.index')->with('status', 'dish-updated');
    }

    public function destroy(Request $request, int $dish): RedirectResponse
    {
        $dishModel = $this->findDish($request, $dish);

        $dishModel->categories()->detach();

        if ($dishModel->image_path !== null) {
            Storage::disk('public')->delete($dishModel->image_path);
        }

        $dishModel->delete();

        return to_route('admin.dishes.index')->with('status', 'dish-deleted');
    }

    private function restaurantFromRequest(Request $request): Restaurant
    {
        $restaurant = $request->user()?->primaryRestaurant();

        abort_if($restaurant === null, 404);

        return $restaurant;
    }

    private function findDish(Request $request, int $dishId): Dish
    {
        return $this->restaurantFromRequest($request)
            ->dishes()
            ->with('categories:id,name')
            ->findOrFail($dishId);
    }

    /**
     * @return list<array{id:int,name:string,position:int}>
     */
    private function categoryOptions(Request $request): array
    {
        return $this->restaurantFromRequest($request)
            ->categories()
            ->orderBy('position')
            ->orderBy('name')
            ->get(['id', 'name', 'position'])
            ->map(fn (Category $category): array => [
                'id' => $category->id,
                'name' => $category->name,
                'position' => $category->position,
            ])
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeDishForList(Dish $dish): array
    {
        return [
            'id' => $dish->id,
            'name' => $dish->name,
            'description' => $dish->description,
            'price' => $dish->price,
            'weight' => $dish->weight,
            'is_active' => $dish->is_active,
            'image_url' => $dish->image_path ? Storage::disk('public')->url($dish->image_path) : null,
            'categories' => $dish->categories
                ->sortBy('name')
                ->map(fn (Category $category): array => [
                    'id' => $category->id,
                    'name' => $category->name,
                ])
                ->values()
                ->all(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeDishForForm(Dish $dish): array
    {
        return [
            'id' => $dish->id,
            'name' => $dish->name,
            'description' => $dish->description,
            'weight' => $dish->weight,
            'price' => $dish->price,
            'is_active' => $dish->is_active,
            'image_path' => $dish->image_path,
            'image_url' => $dish->image_path ? Storage::disk('public')->url($dish->image_path) : null,
            'category_ids' => $dish->categories->pluck('id')->map(fn (int $id): string => (string) $id)->all(),
        ];
    }
}
