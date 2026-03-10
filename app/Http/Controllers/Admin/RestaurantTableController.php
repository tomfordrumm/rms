<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\RestaurantTableUpsertRequest;
use App\Models\Restaurant;
use App\Models\RestaurantTable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class RestaurantTableController extends Controller
{
    public function index(Request $request): Response
    {
        $restaurant = $this->restaurantFromRequest($request);

        return Inertia::render('admin/tables/Index', [
            'tables' => $restaurant->tables()
                ->withCount('reservations')
                ->orderBy('name')
                ->orderBy('id')
                ->get()
                ->map(fn (RestaurantTable $table): array => $this->serializeTableForList($table)),
            'status' => $request->session()->get('status'),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('admin/tables/Create', [
            'table' => null,
        ]);
    }

    public function store(RestaurantTableUpsertRequest $request): RedirectResponse
    {
        $restaurant = $this->restaurantFromRequest($request);
        $validated = $request->validated();

        $restaurant->tables()->create([
            'name' => $validated['name'],
            'capacity' => $validated['capacity'],
            'is_active' => $validated['is_active'] ?? false,
        ]);

        return to_route('admin.tables.index')->with('status', 'table-created');
    }

    public function edit(Request $request, int $table): Response
    {
        return Inertia::render('admin/tables/Edit', [
            'table' => $this->serializeTableForForm($this->findTable($request, $table)),
        ]);
    }

    public function update(RestaurantTableUpsertRequest $request, int $table): RedirectResponse
    {
        $tableModel = $this->findTable($request, $table);
        $validated = $request->validated();

        $tableModel->update([
            'name' => $validated['name'],
            'capacity' => $validated['capacity'],
            'is_active' => $validated['is_active'] ?? false,
        ]);

        return to_route('admin.tables.index')->with('status', 'table-updated');
    }

    public function destroy(Request $request, int $table): RedirectResponse
    {
        $tableModel = $this->findTable($request, $table);

        if ($tableModel->reservations()->exists()) {
            return to_route('admin.tables.index')->with('status', 'table-delete-blocked');
        }

        $tableModel->delete();

        return to_route('admin.tables.index')->with('status', 'table-deleted');
    }

    private function restaurantFromRequest(Request $request): Restaurant
    {
        $restaurant = $request->user()?->primaryRestaurant();

        abort_if($restaurant === null, 404);

        return $restaurant;
    }

    private function findTable(Request $request, int $tableId): RestaurantTable
    {
        return $this->restaurantFromRequest($request)
            ->tables()
            ->withCount('reservations')
            ->findOrFail($tableId);
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeTableForList(RestaurantTable $table): array
    {
        return [
            'id' => $table->id,
            'name' => $table->name,
            'capacity' => $table->capacity,
            'is_active' => $table->is_active,
            'reservations_count' => $table->reservations_count ?? 0,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeTableForForm(RestaurantTable $table): array
    {
        return [
            'id' => $table->id,
            'name' => $table->name,
            'capacity' => $table->capacity,
            'is_active' => $table->is_active,
        ];
    }
}
