<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\RestaurantUpsertRequest;
use App\Models\Restaurant;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class RestaurantController extends Controller
{
    public function edit(Request $request): Response
    {
        $restaurant = $request->user()->primaryRestaurant();

        return Inertia::render('settings/Restaurant', [
            'restaurant' => $restaurant ? $this->serializeRestaurant($restaurant) : null,
            'isOnboarding' => $restaurant === null,
            'status' => $request->session()->get('status'),
        ]);
    }

    public function store(RestaurantUpsertRequest $request): RedirectResponse
    {
        $user = $request->user();
        $restaurant = $user->primaryRestaurant();

        if ($restaurant !== null) {
            return to_route('restaurant.edit');
        }

        $restaurant = Restaurant::query()->create($this->payloadFromRequest($request));
        $user->restaurants()->syncWithoutDetaching([$restaurant->id]);

        return to_route('restaurant.edit')->with('status', 'restaurant-created');
    }

    public function update(RestaurantUpsertRequest $request): RedirectResponse
    {
        $restaurant = $request->user()->primaryRestaurant();

        abort_if($restaurant === null, 404);

        $restaurant->update($this->payloadFromRequest($request, $restaurant));

        return to_route('restaurant.edit')->with('status', 'restaurant-updated');
    }

    /**
     * @return array<string, mixed>
     */
    private function payloadFromRequest(RestaurantUpsertRequest $request, ?Restaurant $restaurant = null): array
    {
        $validated = $request->validated();

        $payload = [
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'slug' => $validated['slug'],
            'contacts' => $validated['contacts'] ?? null,
            'work_hours' => $validated['work_hours'] ?? null,
            'open_time' => $validated['open_time'],
            'close_time' => $validated['close_time'],
            'closed_dates' => empty($validated['closed_dates'])
                ? null
                : json_encode(array_values($validated['closed_dates']), JSON_THROW_ON_ERROR),
        ];

        if ($request->hasFile('logo')) {
            $payload['logo_path'] = $request->file('logo')->store('restaurants/logos', 'public');
        } elseif ($restaurant === null) {
            $payload['logo_path'] = null;
        }

        if ($request->hasFile('cover')) {
            $payload['cover_path'] = $request->file('cover')->store('restaurants/covers', 'public');
        } elseif ($restaurant === null) {
            $payload['cover_path'] = null;
        }

        return $payload;
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeRestaurant(Restaurant $restaurant): array
    {
        $closedDates = [];

        if (is_string($restaurant->closed_dates) && $restaurant->closed_dates !== '') {
            $decoded = json_decode($restaurant->closed_dates, true);
            $closedDates = is_array($decoded) ? array_values($decoded) : [];
        }

        return [
            'id' => $restaurant->id,
            'name' => $restaurant->name,
            'description' => $restaurant->description,
            'slug' => $restaurant->slug,
            'contacts' => $restaurant->contacts,
            'work_hours' => $restaurant->work_hours,
            'open_time' => $this->trimSeconds($restaurant->open_time),
            'close_time' => $this->trimSeconds($restaurant->close_time),
            'closed_dates' => $closedDates,
            'logo_path' => $restaurant->logo_path,
            'cover_path' => $restaurant->cover_path,
            'logo_url' => $restaurant->logo_path ? Storage::disk('public')->url($restaurant->logo_path) : null,
            'cover_url' => $restaurant->cover_path ? Storage::disk('public')->url($restaurant->cover_path) : null,
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
