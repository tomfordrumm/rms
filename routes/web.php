<?php

use App\Http\Controllers\Admin\CategoryController;
use App\Http\Controllers\Admin\DishController;
use App\Http\Controllers\Admin\RestaurantTableController;
use Illuminate\Support\Facades\Route;
use Laravel\Fortify\Features;

Route::inertia('/', 'Welcome', [
    'canRegister' => Features::enabled(Features::registration()),
])->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::middleware('restaurant.required')->group(function () {
        Route::inertia('dashboard', 'Dashboard')->name('dashboard');

        Route::prefix('admin')->name('admin.')->group(function () {
            Route::resource('categories', CategoryController::class)
                ->except('show');

            Route::resource('dishes', DishController::class)
                ->except('show');

            Route::resource('tables', RestaurantTableController::class)
                ->except('show');
        });
    });
});

require __DIR__.'/settings.php';
