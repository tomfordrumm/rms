<?php

namespace App\Actions\PublicReservations;

use App\Models\Reservation;
use App\Models\Restaurant;
use App\Models\RestaurantTable;
use Carbon\CarbonImmutable;

class BuildReservationAvailabilityAction
{
    public function __construct(
        private readonly GenerateReservationSlotsAction $generateReservationSlots,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function handle(
        Restaurant $restaurant,
        int $peopleCount = 2,
        ?string $selectedDate = null,
        ?string $selectedTime = null,
        ?int $excludeReservationId = null,
    ): array {
        $today = CarbonImmutable::today();
        $calendarDates = collect(range(0, 29))
            ->map(fn (int $offset): string => $today->addDays($offset)->format('Y-m-d'))
            ->values()
            ->all();

        $closedDates = $this->closedDates($restaurant);
        $activeTables = $restaurant->tables()
            ->where('is_active', true)
            ->orderBy('capacity')
            ->orderBy('name')
            ->orderBy('id')
            ->get(['id', 'name', 'capacity']);

        $maxPartySize = (int) ($activeTables->max('capacity') ?? 0);
        $normalizedPeopleCount = max(1, $peopleCount);
        $bookingEnabled = $maxPartySize > 0
            && filled($restaurant->open_time)
            && filled($restaurant->close_time);

        $bookableDates = [];

        foreach ($calendarDates as $date) {
            if (in_array($date, $closedDates, true)) {
                continue;
            }

            if ($this->slotOptionsForDate($restaurant, $activeTables, $date, $normalizedPeopleCount, $excludeReservationId) !== []) {
                $bookableDates[] = $date;
            }
        }

        $selectedDateSlots = [];
        $selectedTimeTables = [];

        if ($selectedDate !== null && in_array($selectedDate, $calendarDates, true) && ! in_array($selectedDate, $closedDates, true)) {
            $selectedDateSlots = $this->slotOptionsForDate(
                $restaurant,
                $activeTables,
                $selectedDate,
                $normalizedPeopleCount,
                $excludeReservationId,
            );

            if (
                $selectedTime !== null
                && collect($selectedDateSlots)->contains(fn (array $slot): bool => $slot['value'] === $selectedTime)
            ) {
                $selectedTimeTables = $this->availableTablesForSlot(
                    $restaurant,
                    $activeTables,
                    $selectedDate,
                    $selectedTime,
                    $normalizedPeopleCount,
                    $excludeReservationId,
                );
            }
        }

        return [
            'calendar_dates' => $calendarDates,
            'bookable_dates' => $bookableDates,
            'closed_dates' => $closedDates,
            'selected_date_slots' => $selectedDateSlots,
            'selected_time_tables' => $selectedTimeTables,
            'booking_rules' => [
                'slot_duration_minutes' => 60,
                'min_party_size' => 1,
                'max_party_size' => $maxPartySize,
                'timezone_note' => config('app.timezone'),
            ],
            'booking_enabled' => $bookingEnabled,
            'booking_notice' => $this->bookingNotice($bookingEnabled, $maxPartySize),
        ];
    }

    /**
     * @param  \Illuminate\Support\Collection<int, RestaurantTable>  $activeTables
     * @return list<array{value:string,label:string,available_tables_count:int}>
     */
    private function slotOptionsForDate(
        Restaurant $restaurant,
        \Illuminate\Support\Collection $activeTables,
        string $date,
        int $peopleCount,
        ?int $excludeReservationId = null,
    ): array {
        $slots = $this->generateReservationSlots->handle(
            $restaurant,
            CarbonImmutable::parse($date),
        );

        if ($slots === []) {
            return [];
        }

        $eligibleTables = $activeTables
            ->filter(fn (RestaurantTable $table): bool => $table->capacity >= $peopleCount)
            ->values();

        if ($eligibleTables->isEmpty()) {
            return [];
        }

        $reservedTableIdsByTime = Reservation::query()
            ->where('restaurant_id', $restaurant->id)
            ->whereDate('date', $date)
            ->where('status', Reservation::STATUS_ACTIVE)
            ->whereIn('table_id', $eligibleTables->pluck('id'))
            ->when($excludeReservationId !== null, fn ($query) => $query->whereKeyNot($excludeReservationId))
            ->get(['table_id', 'time'])
            ->groupBy(fn (Reservation $reservation): string => substr((string) $reservation->time, 0, 5))
            ->map(fn (\Illuminate\Support\Collection $reservations): \Illuminate\Support\Collection => $reservations
                ->pluck('table_id')
                ->unique()
                ->values());

        $options = [];

        foreach ($slots as $slot) {
            $reservedIds = $reservedTableIdsByTime->get($slot, collect());
            $availableCount = $eligibleTables
                ->reject(fn (RestaurantTable $table): bool => $reservedIds->contains($table->id))
                ->count();

            if ($availableCount > 0) {
                $options[] = [
                    'value' => $slot,
                    'label' => $this->slotLabel($slot),
                    'available_tables_count' => $availableCount,
                ];
            }
        }

        return $options;
    }

    /**
     * @param  \Illuminate\Support\Collection<int, RestaurantTable>  $activeTables
     * @return list<array{id:int,name:string,capacity:int}>
     */
    private function availableTablesForSlot(
        Restaurant $restaurant,
        \Illuminate\Support\Collection $activeTables,
        string $date,
        string $time,
        int $peopleCount,
        ?int $excludeReservationId = null,
    ): array {
        $eligibleTables = $activeTables
            ->filter(fn (RestaurantTable $table): bool => $table->capacity >= $peopleCount)
            ->values();

        if ($eligibleTables->isEmpty()) {
            return [];
        }

        $reservedIds = Reservation::query()
            ->where('restaurant_id', $restaurant->id)
            ->whereDate('date', $date)
            ->where('time', $time.':00')
            ->where('status', Reservation::STATUS_ACTIVE)
            ->when($excludeReservationId !== null, fn ($query) => $query->whereKeyNot($excludeReservationId))
            ->pluck('table_id');

        return $eligibleTables
            ->reject(fn (RestaurantTable $table): bool => $reservedIds->contains($table->id))
            ->map(fn (RestaurantTable $table): array => [
                'id' => $table->id,
                'name' => $table->name,
                'capacity' => $table->capacity,
            ])
            ->values()
            ->all();
    }

    /**
     * @return list<string>
     */
    private function closedDates(Restaurant $restaurant): array
    {
        if (! is_string($restaurant->closed_dates) || $restaurant->closed_dates === '') {
            return [];
        }

        $decoded = json_decode($restaurant->closed_dates, true);

        return is_array($decoded)
            ? array_values(array_filter($decoded, fn (mixed $date): bool => is_string($date) && $date !== ''))
            : [];
    }

    private function bookingNotice(bool $bookingEnabled, int $maxPartySize): ?string
    {
        if ($bookingEnabled) {
            return null;
        }

        if ($maxPartySize === 0) {
            return 'Reservations are unavailable because no active tables have been configured.';
        }

        return 'Reservations are unavailable until valid opening and closing hours are configured.';
    }

    private function slotLabel(string $slot): string
    {
        return CarbonImmutable::createFromFormat('H:i', $slot)->format('g:i A');
    }
}
