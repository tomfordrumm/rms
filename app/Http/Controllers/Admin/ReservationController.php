<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ReservationUpdateRequest;
use App\Models\Reservation;
use App\Models\Restaurant;
use App\Models\RestaurantTable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ReservationController extends Controller
{
    public function index(Request $request): Response
    {
        $restaurant = $this->restaurantFromRequest($request);

        $query = $restaurant->reservations()
            ->with('restaurantTable:id,name')
            ->when(
                filled($request->string('date')->toString()),
                fn ($builder) => $builder->whereDate('date', $request->string('date')->toString()),
            )
            ->when(
                filled($request->string('email')->toString()),
                fn ($builder) => $builder->where('customer_email', 'like', '%'.$request->string('email')->toString().'%'),
            )
            ->when(
                in_array($request->string('status')->toString(), [Reservation::STATUS_ACTIVE, Reservation::STATUS_CANCELLED], true),
                fn ($builder) => $builder->where('status', $request->string('status')->toString()),
            )
            ->orderByDesc('date')
            ->orderByDesc('time')
            ->orderByDesc('id');

        return Inertia::render('admin/reservations/Index', [
            'reservations' => $query->get()->map(
                fn (Reservation $reservation): array => $this->serializeReservationForList($reservation),
            ),
            'filters' => [
                'date' => $request->string('date')->toString(),
                'email' => $request->string('email')->toString(),
                'status' => $request->string('status')->toString(),
            ],
            'statusOptions' => $this->statusOptions(),
            'status' => $request->session()->get('status'),
        ]);
    }

    public function edit(Request $request, int $reservation): Response
    {
        $reservationModel = $this->findReservation($request, $reservation);

        return Inertia::render('admin/reservations/Edit', [
            'reservation' => $this->serializeReservationForForm($reservationModel),
            'tables' => $this->tableOptions($request, $reservationModel->table_id),
            'statusOptions' => $this->statusOptions(),
        ]);
    }

    public function update(ReservationUpdateRequest $request, int $reservation): RedirectResponse
    {
        $reservationModel = $this->findReservation($request, $reservation);
        $validated = $request->validated();

        $reservationModel->update([
            'date' => $validated['date'],
            'time' => $validated['time'],
            'table_id' => $validated['table_id'],
            'people_count' => $validated['people_count'],
        ]);

        return to_route('admin.reservations.index')->with('status', 'reservation-updated');
    }

    public function cancel(Request $request, int $reservation): RedirectResponse
    {
        $reservationModel = $this->findReservation($request, $reservation);

        $reservationModel->update([
            'status' => Reservation::STATUS_CANCELLED,
        ]);

        return to_route('admin.reservations.index')->with('status', 'reservation-cancelled');
    }

    private function restaurantFromRequest(Request $request): Restaurant
    {
        $restaurant = $request->user()?->primaryRestaurant();

        abort_if($restaurant === null, 404);

        return $restaurant;
    }

    private function findReservation(Request $request, int $reservationId): Reservation
    {
        return $this->restaurantFromRequest($request)
            ->reservations()
            ->with('restaurantTable:id,name,capacity,is_active')
            ->findOrFail($reservationId);
    }

    /**
     * @return list<array{id:string,label:string}>
     */
    private function statusOptions(): array
    {
        return [
            ['id' => Reservation::STATUS_ACTIVE, 'label' => 'Active'],
            ['id' => Reservation::STATUS_CANCELLED, 'label' => 'Cancelled'],
        ];
    }

    /**
     * @return list<array{id:number,name:string,capacity:int,is_active:bool}>
     */
    private function tableOptions(Request $request, int $currentTableId): array
    {
        return $this->restaurantFromRequest($request)
            ->tables()
            ->where(function ($query) use ($currentTableId): void {
                $query->where('is_active', true)
                    ->orWhere('id', $currentTableId);
            })
            ->orderBy('name')
            ->get(['id', 'name', 'capacity', 'is_active'])
            ->map(fn (RestaurantTable $table): array => [
                'id' => $table->id,
                'name' => $table->name,
                'capacity' => $table->capacity,
                'is_active' => $table->is_active,
            ])
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeReservationForList(Reservation $reservation): array
    {
        return [
            'id' => $reservation->id,
            'customer_name' => $reservation->customer_name,
            'customer_email' => $reservation->customer_email,
            'people_count' => $reservation->people_count,
            'date' => $reservation->date?->format('Y-m-d'),
            'time' => substr((string) $reservation->time, 0, 5),
            'status' => $reservation->status,
            'table' => $reservation->restaurantTable
                ? [
                    'id' => $reservation->restaurantTable->id,
                    'name' => $reservation->restaurantTable->name,
                ]
                : null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeReservationForForm(Reservation $reservation): array
    {
        return [
            'id' => $reservation->id,
            'customer_name' => $reservation->customer_name,
            'customer_email' => $reservation->customer_email,
            'people_count' => $reservation->people_count,
            'date' => $reservation->date?->format('Y-m-d'),
            'time' => substr((string) $reservation->time, 0, 5),
            'status' => $reservation->status,
            'table_id' => $reservation->table_id,
            'table' => $reservation->restaurantTable
                ? [
                    'id' => $reservation->restaurantTable->id,
                    'name' => $reservation->restaurantTable->name,
                    'capacity' => $reservation->restaurantTable->capacity,
                    'is_active' => $reservation->restaurantTable->is_active,
                ]
                : null,
        ];
    }
}
