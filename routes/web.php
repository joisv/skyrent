<?php

use Illuminate\Support\Facades\Route;

Route::view('/', 'welcome')
    ->name('welcome');

Route::middleware(['auth', 'role:super-admin|admin'])
    ->group(function () {
        Route::view('dashboard', 'dashboard')
            ->name('dashboard');

        Route::view('iphones', 'iphones')
            ->name('iphones');

        Route::view('iphones/create', 'iphones.create')
            ->name('iphones.create');

        Route::view('profile', 'profile')
            ->name('profile');
    });


require __DIR__ . '/auth.php';
