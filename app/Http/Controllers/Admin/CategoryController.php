<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\CategoryUpsertRequest;
use App\Models\Category;
use App\Models\Restaurant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CategoryController extends Controller
{
    public function index(Request $request): Response
    {
        $restaurant = $this->restaurantFromRequest($request);

        return Inertia::render('admin/categories/Index', [
            'categories' => $restaurant->categories()
                ->withCount('dishes')
                ->orderBy('position')
                ->orderBy('name')
                ->get()
                ->map(fn (Category $category): array => $this->serializeCategory($category)),
            'status' => $request->session()->get('status'),
        ]);
    }

    public function create(Request $request): Response
    {
        return Inertia::render('admin/categories/Create', [
            'category' => null,
            'suggestedPosition' => $this->nextPosition($this->restaurantFromRequest($request)),
        ]);
    }

    public function store(CategoryUpsertRequest $request): RedirectResponse|JsonResponse
    {
        $restaurant = $this->restaurantFromRequest($request);

        $category = $restaurant->categories()->create([
            'name' => $request->validated('name'),
            'description' => $request->validated('description'),
            'position' => $request->validated('position') ?? $this->nextPosition($restaurant),
        ]);

        if ($request->expectsJson()) {
            return response()->json([
                'category' => $this->serializeCategory($category->loadCount('dishes')),
            ], 201);
        }

        return to_route('admin.categories.index')->with('status', 'category-created');
    }

    public function edit(Request $request, int $category): Response
    {
        return Inertia::render('admin/categories/Edit', [
            'category' => $this->serializeCategory($this->findCategory($request, $category)),
            'suggestedPosition' => null,
        ]);
    }

    public function update(CategoryUpsertRequest $request, int $category): RedirectResponse
    {
        $categoryModel = $this->findCategory($request, $category);

        $categoryModel->update([
            'name' => $request->validated('name'),
            'description' => $request->validated('description'),
            'position' => $request->validated('position') ?? $categoryModel->position,
        ]);

        return to_route('admin.categories.index')->with('status', 'category-updated');
    }

    public function destroy(Request $request, int $category): RedirectResponse
    {
        $categoryModel = $this->findCategory($request, $category);

        if ($categoryModel->dishes()->exists()) {
            return to_route('admin.categories.index')->with('status', 'category-delete-blocked');
        }

        $categoryModel->delete();

        return to_route('admin.categories.index')->with('status', 'category-deleted');
    }

    private function restaurantFromRequest(Request $request): Restaurant
    {
        $restaurant = $request->user()?->primaryRestaurant();

        abort_if($restaurant === null, 404);

        return $restaurant;
    }

    private function findCategory(Request $request, int $categoryId): Category
    {
        return $this->restaurantFromRequest($request)
            ->categories()
            ->withCount('dishes')
            ->findOrFail($categoryId);
    }

    private function nextPosition(Restaurant $restaurant): int
    {
        return (int) $restaurant->categories()->max('position') + 1;
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
            'position' => $category->position,
            'dishes_count' => $category->dishes_count ?? 0,
        ];
    }
}
