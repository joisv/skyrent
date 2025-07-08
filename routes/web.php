<?php

use App\Models\Iphones;
use Illuminate\Support\Facades\Route;

Route::view('/', 'welcome')
    ->name('welcome');

Route::view('detail', 'detail')
    ->name('detail');

Route::middleware(['auth', 'role:super-admin|admin'])
    ->group(function () {
        Route::view('dashboard', 'dashboard')
            ->name('dashboard');

        Route::view('iphones', 'iphones')
            ->name('iphones');

        Route::view('bookings', 'bookings')
            ->name('bookings');

        Route::get('iphones/edit/{iphone:id}', function (Iphones $iphone) {
            return view('iphones.edit', [
                'iphone' => $iphone
            ]);
        })->name('iphones.edit');

        Route::view('iphones/create', 'iphones.create')
            ->name('iphones.create');

        Route::view('profile', 'profile')
            ->name('profile');

        Route::view('settings/basic', 'settings.basic')
            ->name('settings.basic');
    });


require __DIR__ . '/auth.php';
