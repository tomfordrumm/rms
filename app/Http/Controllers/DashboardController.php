<?php

namespace App\Http\Controllers;

use App\Models\Reservation;
use App\Models\Restaurant;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $restaurant = $request->user()?->primaryRestaurant();

        abort_if($restaurant === null, 404);

        $today = CarbonImmutable::today();
        $trendStart = $today->subDays(29);

        return Inertia::render('Dashboard', [
            'stats' => [
                'dishes_count' => $restaurant->dishes()->count(),
                'tables_count' => $restaurant->tables()->count(),
                'active_reservations_count' => $restaurant->reservations()
                    ->where('status', Reservation::STATUS_ACTIVE)
                    ->whereDate('date', '>=', $today->toDateString())
                    ->count(),
            ],
            'reservation_trend' => $this->buildReservationTrend($restaurant, $trendStart, $today),
        ]);
    }

    /**
     * @return array<int, array{date:string,label:string,count:int}>
     */
    private function buildReservationTrend(
        Restaurant $restaurant,
        CarbonImmutable $trendStart,
        CarbonImmutable $trendEnd,
    ): array {
        /** @var \Illuminate\Support\Collection<string, int> $countsByDate */
        $countsByDate = $restaurant->reservations()
            ->selectRaw('DATE(date) as reservation_date, COUNT(*) as aggregate')
            ->where('status', Reservation::STATUS_ACTIVE)
            ->whereDate('date', '>=', $trendStart->toDateString())
            ->whereDate('date', '<=', $trendEnd->toDateString())
            ->groupBy(DB::raw('DATE(date)'))
            ->orderBy(DB::raw('DATE(date)'))
            ->get()
            ->mapWithKeys(fn (object $row): array => [
                CarbonImmutable::parse((string) $row->reservation_date)->toDateString() => (int) $row->aggregate,
            ]);

        return collect(range(0, 29))
            ->map(function (int $offset) use ($trendStart, $countsByDate): array {
                $date = $trendStart->addDays($offset);
                $dateKey = $date->toDateString();

                return [
                    'date' => $dateKey,
                    'label' => $date->format('M j'),
                    'count' => (int) ($countsByDate[$dateKey] ?? 0),
                ];
            })
            ->all();
    }
}
