<?php

use App\Http\Controllers\Admin\CategoryController;
use App\Http\Controllers\Admin\DishController;
use App\Http\Controllers\Admin\QrController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\MagicOrderController;
use App\Http\Controllers\PublicReservationController;
use App\Http\Controllers\RestaurantMenuController;
use App\Http\Controllers\Admin\ReservationController;
use App\Http\Controllers\Admin\RestaurantTableController;
use Illuminate\Support\Facades\Route;
use Laravel\Fortify\Features;

Route::inertia('/', 'Welcome', [
    'canRegister' => Features::enabled(Features::registration()),
])->name('home');

Route::get('/r/{slug}/menu', RestaurantMenuController::class)
    ->name('restaurants.menu');
Route::post('/r/{slug}/magic-order', MagicOrderController::class)
    ->middleware('throttle:5,1')
    ->name('restaurants.magic-order');
Route::controller(PublicReservationController::class)
    ->prefix('/r/{slug}/booking')
    ->name('restaurants.booking.')
    ->group(function (): void {
        Route::get('/', 'showBookingPage')->name('show');
        Route::get('/availability', 'availability')
            ->middleware('throttle:30,1')
            ->name('availability');
        Route::post('/', 'store')
            ->middleware('throttle:10,1')
            ->name('store');
        Route::get('/success/{token}', 'showSuccess')->name('success');
        Route::get('/manage/{token}', 'showManage')->name('manage');
        Route::patch('/manage/{token}', 'update')
            ->middleware('throttle:10,1')
            ->name('update');
        Route::patch('/manage/{token}/cancel', 'cancel')
            ->middleware('throttle:10,1')
            ->name('cancel');
    });

Route::middleware(['auth', 'verified'])->group(function () {
    Route::middleware('restaurant.required')->group(function () {
        Route::get('dashboard', DashboardController::class)->name('dashboard');

        Route::prefix('admin')->name('admin.')->group(function () {
            Route::resource('categories', CategoryController::class)
                ->except('show');

            Route::resource('dishes', DishController::class)
                ->except('show');

            Route::resource('tables', RestaurantTableController::class)
                ->except('show');

            Route::resource('reservations', ReservationController::class)
                ->only(['index', 'edit', 'update']);
            Route::patch('reservations/{reservation}/cancel', [ReservationController::class, 'cancel'])
                ->name('reservations.cancel');

            Route::get('qr', QrController::class)->name('qr.index');
        });
    });
});

require __DIR__.'/settings.php';
