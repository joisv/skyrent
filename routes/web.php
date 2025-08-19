<?php

use App\Http\Controllers\Client\PageController;
use App\Models\Faq;
use App\Models\Iphones;
use App\Settings\GeneralSettings;
use Illuminate\Support\Facades\Route;

Route::view('/', [PageController::class, 'welcome'])->name('welcome');

Route::get('cara-sewa', [PageController::class, 'howtorent'])->name('howtorent');
Route::get('kebijakan-privasi', [PageController::class, 'privacy'])->name('privacy');
Route::get('syarat-ketentuan', [PageController::class, 'terms'])->name('terms');

Route::get('detail/{iphones:slug}', [PageController::class, 'detail'])->name('detail');

Route::get('faqs', [PageController::class, 'faq'])->name('faqs');

Route::get('contacts', [PageController::class, 'contacts'])->name('contacts');

Route::get('products', [PageController::class, 'products'])->name('products');

Route::middleware(['auth', 'role:super-admin|admin'])->prefix('admin')
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

        Route::view('reports/revenue', 'reports.revenue')
            ->name('reports.revenue');

        Route::view('reports/top-device', 'reports.top-device')
            ->name('reports.topdevice');

        Route::view('settings/users', 'settings.users')
            ->name('settings.users');

        Route::view('settings/sliders', 'settings.sliders')->name('settings.sliders');

        Route::view('faq', 'faq')
            ->name('faq');

        Route::view('payments', 'payments')
            ->name('payments');
    });


require __DIR__ . '/auth.php';
