<?php

namespace App\Http\Controllers;

use App\Actions\PublicReservations\BuildReservationAvailabilityAction;
use App\Actions\PublicReservations\CancelPublicReservationAction;
use App\Actions\PublicReservations\CreatePublicReservationAction;
use App\Actions\PublicReservations\UpdatePublicReservationAction;
use App\Actions\PublicRestaurants\SerializePublicRestaurantAction;
use App\Http\Requests\PublicReservationStoreRequest;
use App\Http\Requests\PublicReservationUpdateRequest;
use App\Mail\ReservationUpdatedAdminMail;
use App\Mail\ReservationUpdatedGuestMail;
use App\Mail\ReservationConfirmedMail;
use App\Models\Reservation;
use App\Models\Restaurant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Inertia\Inertia;
use Inertia\Response;

class PublicReservationController extends Controller
{
    public function __construct(
        private readonly SerializePublicRestaurantAction $serializeRestaurant,
        private readonly BuildReservationAvailabilityAction $buildAvailability,
        private readonly CreatePublicReservationAction $createReservation,
        private readonly UpdatePublicReservationAction $updateReservation,
        private readonly CancelPublicReservationAction $cancelReservation,
    ) {}

    public function showBookingPage(string $slug): Response
    {
        $restaurant = $this->findRestaurantOrFail($slug);
        $initialPeopleCount = 2;
        $availability = $this->buildAvailability->handle($restaurant, $initialPeopleCount);
        $selectedDate = $availability['bookable_dates'][0] ?? null;

        if ($selectedDate !== null) {
            $availability = $this->buildAvailability->handle($restaurant, $initialPeopleCount, $selectedDate);
        }

        return Inertia::render('restaurants/Booking', [
            'restaurant' => $this->serializeRestaurant->handle($restaurant),
            'initialAvailability' => $availability,
            'initialState' => [
                'people_count' => $initialPeopleCount,
                'date' => $selectedDate,
                'time' => null,
                'table_id' => null,
            ],
        ]);
    }

    public function availability(Request $request, string $slug): JsonResponse
    {
        $restaurant = $this->findRestaurantOrFail($slug);
        $peopleCount = max(1, (int) $request->integer('people_count', 2));
        $date = $request->string('date')->toString() ?: null;
        $time = $request->string('time')->toString() ?: null;
        $excludeReservationId = null;

        $token = $request->string('token')->toString();

        if ($token !== '') {
            $reservation = Reservation::query()
                ->where('token', $token)
                ->whereHas('restaurant', fn ($query) => $query->where('slug', $slug))
                ->first();

            $excludeReservationId = $reservation?->id;
        }

        return response()->json(
            $this->buildAvailability->handle($restaurant, $peopleCount, $date, $time, $excludeReservationId),
        );
    }

    public function store(PublicReservationStoreRequest $request, string $slug): RedirectResponse
    {
        $restaurant = $request->restaurant() ?? $this->findRestaurantOrFail($slug);
        $reservation = $this->createReservation->handle($restaurant, $request->reservationPayload());
        $reservation->loadMissing('restaurantTable');

        Mail::to($reservation->customer_email)->send(
            new ReservationConfirmedMail(
                reservation: $reservation,
                restaurant: $this->serializeRestaurant->handle($restaurant),
                manageUrl: route('restaurants.booking.manage', [
                    'slug' => $restaurant->slug,
                    'token' => $reservation->token,
                ]),
            ),
        );

        return to_route('restaurants.booking.success', [
            'slug' => $restaurant->slug,
            'token' => $reservation->token,
        ]);
    }

    public function showSuccess(string $slug, string $token): Response
    {
        $reservation = $this->findReservationOrFail($slug, $token);

        return Inertia::render('restaurants/BookingSuccess', [
            'restaurant' => $this->serializeRestaurant->handle($reservation->restaurant),
            'reservation' => $this->serializeReservation($reservation),
            'manage_url' => route('restaurants.booking.manage', [
                'slug' => $slug,
                'token' => $token,
            ]),
        ]);
    }

    public function showManage(Request $request, string $slug, string $token): Response
    {
        $reservation = $this->findReservationOrFail($slug, $token);
        $availability = $this->buildAvailability->handle(
            $reservation->restaurant,
            $reservation->people_count,
            $reservation->date?->format('Y-m-d'),
            substr((string) $reservation->time, 0, 5),
            $reservation->id,
        );

        return Inertia::render('restaurants/BookingManage', [
            'restaurant' => $this->serializeRestaurant->handle($reservation->restaurant),
            'reservation' => $this->serializeReservation($reservation),
            'initialAvailability' => $availability,
            'status' => $request->session()->get('status'),
        ]);
    }

    public function update(PublicReservationUpdateRequest $request, string $slug, string $token): RedirectResponse
    {
        $reservation = $request->reservation() ?? $this->findReservationOrFail($slug, $token);
        $previousReservation = $this->reservationSnapshot($reservation);
        $updatedReservation = $this->updateReservation->handle($reservation, $request->reservationPayload());
        $restaurantPayload = $this->serializeRestaurant->handle($updatedReservation->restaurant);
        $manageUrl = route('restaurants.booking.manage', [
            'slug' => $slug,
            'token' => $token,
        ]);

        Mail::to($updatedReservation->customer_email)->send(
            new ReservationUpdatedGuestMail(
                reservation: $updatedReservation,
                restaurant: $restaurantPayload,
                previousReservation: $previousReservation,
                manageUrl: $manageUrl,
            ),
        );

        $adminEmails = $updatedReservation->restaurant
            ->users()
            ->whereNotNull('email')
            ->pluck('email')
            ->filter(fn (?string $email): bool => is_string($email) && $email !== '')
            ->unique()
            ->values()
            ->all();

        if ($adminEmails !== []) {
            Mail::to($adminEmails)->send(
                new ReservationUpdatedAdminMail(
                    reservation: $updatedReservation,
                    restaurant: $restaurantPayload,
                    previousReservation: $previousReservation,
                    manageUrl: $manageUrl,
                ),
            );
        }

        return to_route('restaurants.booking.manage', [
            'slug' => $slug,
            'token' => $token,
        ])->with('status', 'reservation-updated');
    }

    public function cancel(string $slug, string $token): RedirectResponse
    {
        $reservation = $this->findReservationOrFail($slug, $token);

        $this->cancelReservation->handle($reservation);

        return to_route('restaurants.booking.manage', [
            'slug' => $slug,
            'token' => $token,
        ])->with('status', 'reservation-cancelled');
    }

    private function findRestaurantOrFail(string $slug): Restaurant
    {
        return Restaurant::query()
            ->where('slug', $slug)
            ->firstOrFail();
    }

    private function findReservationOrFail(string $slug, string $token): Reservation
    {
        return Reservation::query()
            ->where('token', $token)
            ->whereHas('restaurant', fn ($query) => $query->where('slug', $slug))
            ->with(['restaurant', 'restaurantTable'])
            ->firstOrFail();
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeReservation(Reservation $reservation): array
    {
        return [
            'id' => $reservation->id,
            'token' => $reservation->token,
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
                    'capacity' => $reservation->restaurantTable->capacity,
                ]
                : null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function reservationSnapshot(Reservation $reservation): array
    {
        return [
            'date' => $reservation->date?->format('Y-m-d'),
            'time' => substr((string) $reservation->time, 0, 5),
            'people_count' => $reservation->people_count,
            'table_name' => $reservation->restaurantTable?->name ?? 'Assigned table',
        ];
    }
}
