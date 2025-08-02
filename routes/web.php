<?php

use App\Models\Faq;
use App\Models\Iphones;
use App\Settings\GeneralSettings;
use Illuminate\Support\Facades\Route;

Route::view('/', 'welcome')
    ->name('welcome');

Route::get('cara-sewa', function (GeneralSettings $setting) {
    return view('how-to-rent', [
        'how_to_rent' => $setting->how_to_rent
    ]);
})->name('howtorent');

Route::get('detail/{iphones:slug}', function (Iphones $iphones) {
    return view('detail', [
        'iphone' => $iphones->load(['gallery', 'bookings'])
    ]);
})->name('detail');

Route::get('faqs', function () {

    $faq = Faq::orderBy('created_at', 'desc')->get();

    return view('user-faq', [
        'faqs' => $faq
    ]);
})->name('faqs');

Route::get('contacts', function (){

    return view('contacts');
    
})->name('contacts');

Route::get('products', function() {
    return view('products');
})->name('products');

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

        Route::view('faq', 'faq')
            ->name('faq');

        Route::view('payments', 'payments')
            ->name('payments');
    });


require __DIR__ . '/auth.php';
