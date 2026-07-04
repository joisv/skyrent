<?php

use App\Http\Controllers\Client\PageController;
use App\Models\Iphones;
use Illuminate\Support\Facades\Route;
use App\Models\Booking;


Route::view('/', [PageController::class, 'welcome'])->name('welcome');
Route::get('cara-sewa', [PageController::class, 'howtorent'])->name('howtorent');
Route::get('kebijakan-privasi', [PageController::class, 'privacy'])->name('privacy');
Route::get('syarat-ketentuan', [PageController::class, 'terms'])->name('terms');
Route::get('detail/{iphones:slug}', [PageController::class, 'detail'])->name('detail');
Route::get('faqs', [PageController::class, 'faq'])->name('faqs');
Route::get('contacts', [PageController::class, 'contacts'])->name('contacts');
Route::get('products', [PageController::class, 'products'])->name('products');
Route::get('booking-status', [PageController::class, 'bookingStatus'])->name('booking.status');
Route::get('prices', [PageController::class, 'prices'])->name('prices');

Route::middleware(['auth', 'role:super-admin|admin|staff'])->prefix('admin')
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

        Route::view('affiliates', 'affiliates')
            ->name('affiliates');

        Route::view('roles-permissions', 'roles-permissions')->name('roles-permissions');
    });

Route::middleware(['auth', 'role:admin|super-admin|affiliate-admin|affiliate'])->prefix('affiliate')
    ->group(function () {
        Route::get('dashboard', function () {
            return view('affiliate.dashboard');
        })->name('affiliate.dashboard');
    });

// vcf export
Route::get('/export-vcf', function () {

    return response()->streamDownload(function () {

        Booking::selectRaw('MIN(customer_name) as customer_name, customer_phone')
            ->groupBy('customer_phone')
            ->cursor()
            ->each(function ($booking) {

                $phone = preg_replace('/[^0-9]/', '', $booking->customer_phone);

                if (str_starts_with($phone, '0')) {
                    $phone = '62' . substr($phone, 1);
                }

                echo "BEGIN:VCARD\r\n";
                echo "VERSION:3.0\r\n";
                echo "FN:{$booking->customer_name} (SkyRental)\r\n";
                echo "TEL;TYPE=CELL:{$phone}\r\n";
                echo "END:VCARD\r\n";
            });
    }, 'customers.vcf', [
        'Content-Type' => 'text/vcard',
    ]);
});

require __DIR__ . '/auth.php';
