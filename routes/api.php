<?php

use App\Http\Controllers\Api\V1\AffiliateController;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\BookingController;
use App\Http\Controllers\Api\V1\DashboardController;
use App\Http\Controllers\Api\V1\IphoneController;
use App\Http\Controllers\Api\V1\PaymentController;
use App\Http\Controllers\Api\V1\ReceiptController;
use App\Http\Controllers\Api\V1\ReturnController;
use App\Http\Controllers\Api\V1\SettingController;
use App\Http\Controllers\Api\V1\UserController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes — SKYRental Admin Mobile
|--------------------------------------------------------------------------
|
| Versioned API routes for the SKYRental mobile application.
|
*/

Route::prefix('v1')->group(function () {
    // Health & Connectivity Check
    Route::get('/health', fn() => response()->json(['status' => 'ok', 'app' => 'skyrent', 'version' => '1.0', 'time' => now()->toIso8601String()]));
    Route::get('/ping', fn() => response()->json(['status' => 'pong', 'message' => 'SKYRental API is online']));

    // Authentication endpoints
    Route::post('/auth/login', [AuthController::class, 'login'])->name('api.v1.auth.login');
    Route::post('/login', [AuthController::class, 'login'])->name('api.v1.login');

    Route::middleware('auth:sanctum')->group(function () {
        Route::post('/auth/logout', [AuthController::class, 'logout'])->name('api.v1.auth.logout');
        Route::post('/logout', [AuthController::class, 'logout'])->name('api.v1.logout');
        Route::get('/auth/me', [AuthController::class, 'me'])->name('api.v1.auth.me');
        Route::get('/auth/user', [AuthController::class, 'me'])->name('api.v1.auth.user');
        Route::get('/user', [AuthController::class, 'me'])->name('api.v1.user');
        Route::post('/auth/change-password', [AuthController::class, 'changePassword'])->name('api.v1.auth.change_password');
        Route::post('/change-password', [AuthController::class, 'changePassword'])->name('api.v1.change_password');

        // Shop Settings modification (Requires Authentication)
        Route::put('/settings/shop', [SettingController::class, 'updateShopSettings'])->name('api.v1.settings.shop.update');
        Route::post('/settings/shop', [SettingController::class, 'updateShopSettings'])->name('api.v1.settings.shop.update_post');
        Route::put('/shop-settings', [SettingController::class, 'updateShopSettings'])->name('api.v1.shop_settings.update');
        Route::post('/shop-settings', [SettingController::class, 'updateShopSettings'])->name('api.v1.shop_settings.update_post');
        Route::put('/settings', [SettingController::class, 'updateShopSettings'])->name('api.v1.settings.update');
    });

    // Public / Operational Shop Settings read endpoints
    Route::get('/settings/shop', [SettingController::class, 'getShopSettings'])->name('api.v1.settings.shop');
    Route::get('/shop-settings', [SettingController::class, 'getShopSettings'])->name('api.v1.shop_settings.get');
    Route::get('/settings', [SettingController::class, 'getShopSettings'])->name('api.v1.settings.index');

    // Operational Dashboard endpoints
    Route::get('/dashboard/summary', [DashboardController::class, 'dailySummary'])->name('api.v1.dashboard.summary');
    Route::get('/dashboard/daily', [DashboardController::class, 'dailySummary'])->name('api.v1.dashboard.daily');
    Route::get('/dashboard/sales-report', [DashboardController::class, 'salesReport'])->name('api.v1.dashboard.sales_report');
    Route::get('/dashboard/sales', [DashboardController::class, 'salesReport'])->name('api.v1.dashboard.sales');
    Route::get('/reports/sales/export', [DashboardController::class, 'exportCsv'])->name('api.v1.reports.sales.export');
    Route::get('/reports/sales', [DashboardController::class, 'salesReport'])->name('api.v1.reports.sales');
    Route::get('/reports/export/csv', [DashboardController::class, 'exportCsv'])->name('api.v1.reports.export.csv');
    Route::get('/dashboard/export/csv', [DashboardController::class, 'exportCsv'])->name('api.v1.dashboard.export.csv');
    Route::get('/reports/export/closing', [DashboardController::class, 'exportClosingEscPos'])->name('api.v1.reports.export.closing');
    Route::get('/dashboard/export/closing', [DashboardController::class, 'exportClosingEscPos'])->name('api.v1.dashboard.export.closing');
    Route::get('/reports/closing', [DashboardController::class, 'exportClosingEscPos'])->name('api.v1.reports.closing');
    Route::get('/dashboard', [DashboardController::class, 'dailySummary'])->name('api.v1.dashboard.index');

    // Bookings endpoints
    Route::get('/bookings/today', [BookingController::class, 'today'])->name('api.v1.bookings.today');
    Route::get('/bookings/search', [BookingController::class, 'search'])->name('api.v1.bookings.search');
    Route::get('/bookings', [BookingController::class, 'index'])->name('api.v1.bookings.index');
    Route::post('/bookings', [BookingController::class, 'store'])->name('api.v1.bookings.store');
    Route::post('/bookings/create', [BookingController::class, 'store'])->name('api.v1.bookings.create');
    Route::get('/bookings/{idOrCode}/verify', [BookingController::class, 'verifyDetail'])->name('api.v1.bookings.verify');
    Route::get('/pickup/verify/{idOrCode}', [BookingController::class, 'verifyDetail'])->name('api.v1.pickup.verify');
    Route::get('/bookings/{idOrCode}', [BookingController::class, 'show'])->name('api.v1.bookings.show');
    Route::delete('/bookings/{idOrCode}', [BookingController::class, 'destroy'])->name('api.v1.bookings.destroy');
    Route::delete('/bookings/{idOrCode}/delete', [BookingController::class, 'destroy']);
    Route::get('/bookings/{idOrCode}/can-extend', [BookingController::class, 'canExtend'])->name('api.v1.bookings.can_extend');
    Route::post('/bookings/{idOrCode}/extend', [BookingController::class, 'extend'])->name('api.v1.bookings.extend');
    Route::post('/bookings/{idOrCode}/tambah-jam', [BookingController::class, 'extend']);
    Route::post('/validate-whatsapp', [BookingController::class, 'validateWhatsApp'])->name('api.v1.validate_whatsapp');
    Route::post('/whatsapp/validate', [BookingController::class, 'validateWhatsApp']);

    // Pickup confirmation & status transition endpoints
    Route::post('/bookings/{idOrCode}/pickup', [BookingController::class, 'confirmPickup'])->name('api.v1.bookings.pickup');
    Route::post('/pickup/confirm/{idOrCode?}', [BookingController::class, 'confirmPickup'])->name('api.v1.pickup.confirm');

    // Payments & Deposit endpoints
    Route::get('/payment-methods', [PaymentController::class, 'methods'])->name('api.v1.payment_methods');
    Route::get('/payments/methods', [PaymentController::class, 'methods'])->name('api.v1.payments.methods');
    Route::get('/payments', [PaymentController::class, 'index'])->name('api.v1.payments.index');
    Route::get('/payments/{idOrCode}', [PaymentController::class, 'show'])->name('api.v1.payments.show');
    Route::get('/bookings/{idOrCode}/payments', [PaymentController::class, 'index'])->name('api.v1.bookings.payments.index');
    Route::post('/bookings/{idOrCode}/payments', [PaymentController::class, 'storeRentalPayment'])->name('api.v1.bookings.payments');
    Route::post('/payments/rental', [PaymentController::class, 'storeRentalPayment'])->name('api.v1.payments.rental');
    Route::post('/bookings/{idOrCode}/deposits', [PaymentController::class, 'storeDeposit'])->name('api.v1.bookings.deposits');
    Route::post('/deposits', [PaymentController::class, 'storeDeposit'])->name('api.v1.deposits.store');
    Route::get('/bookings/{idOrCode}/deposit', [PaymentController::class, 'showDeposit'])->name('api.v1.bookings.deposit');
    Route::get('/deposits/{idOrCode}', [PaymentController::class, 'showDeposit'])->name('api.v1.deposits.show');
    Route::post('/bookings/{idOrCode}/deposits/refund', [PaymentController::class, 'refundDeposit'])->name('api.v1.bookings.deposits.refund');
    Route::post('/deposits/refund', [PaymentController::class, 'refundDeposit'])->name('api.v1.deposits.refund');
    Route::post('/deposits/{idOrCode}/refund', [PaymentController::class, 'refundDeposit'])->name('api.v1.deposits.id_refund');

    // iPhone & physical unit endpoints
    Route::get('/iphones/available', [IphoneController::class, 'available'])->name('api.v1.iphones.available');
    Route::get('/pickup/available-units', [IphoneController::class, 'available'])->name('api.v1.pickup.available_units');
    Route::get('/iphones/summary', [IphoneController::class, 'summary'])->name('api.v1.iphones.summary');
    Route::get('/units/summary', [IphoneController::class, 'summary'])->name('api.v1.units.summary');
    Route::get('/iphones/unit-status', [IphoneController::class, 'index'])->name('api.v1.iphones.unit_status');
    Route::get('/iphones/{idOrAssetCode}/schedule', [IphoneController::class, 'schedule'])->name('api.v1.iphones.schedule');
    Route::get('/units/{idOrAssetCode}/schedule', [IphoneController::class, 'schedule'])->name('api.v1.units.schedule');
    Route::get('/iphones', [IphoneController::class, 'index'])->name('api.v1.iphones.index');
    Route::post('/iphones', [IphoneController::class, 'store'])->name('api.v1.iphones.store');
    Route::get('/iphones/{idOrAssetCode}', [IphoneController::class, 'show'])->name('api.v1.iphones.show');
    Route::put('/iphones/{idOrAssetCode}', [IphoneController::class, 'update'])->name('api.v1.iphones.update');
    Route::post('/iphones/{idOrAssetCode}', [IphoneController::class, 'update']);
    Route::get('/galleries', [IphoneController::class, 'galleries'])->name('api.v1.galleries');
    Route::post('/galleries/upload', [IphoneController::class, 'uploadGallery'])->name('api.v1.galleries.upload');
    Route::post('/galleries', [IphoneController::class, 'uploadGallery']);
    Route::post('/iphones/{idOrAssetCode}/status', [IphoneController::class, 'updateStatus'])->name('api.v1.iphones.update_status');
    Route::put('/iphones/{idOrAssetCode}/status', [IphoneController::class, 'updateStatus']);
    Route::post('/units/{idOrAssetCode}/status', [IphoneController::class, 'updateStatus']);
    Route::put('/units/{idOrAssetCode}/status', [IphoneController::class, 'updateStatus']);

    // Receipt & Struk Printing endpoints
    Route::get('/receipts', [ReceiptController::class, 'index'])->name('api.v1.receipts.index');
    Route::get('/receipts/history', [ReceiptController::class, 'index'])->name('api.v1.receipts.history');
    Route::get('/transactions/completed', [ReceiptController::class, 'index'])->name('api.v1.transactions.completed');
    Route::get('/receipts/{bookingIdOrCode}', [ReceiptController::class, 'show'])->name('api.v1.receipts.show');
    Route::get('/bookings/{idOrCode}/receipt', [ReceiptController::class, 'show'])->name('api.v1.bookings.receipt');

    // Returns & Pengembalian iPhone endpoints
    Route::get('/returns/active-rentals', [ReturnController::class, 'activeRentals'])->name('api.v1.returns.active_rentals');
    Route::get('/returns/active', [ReturnController::class, 'activeRentals'])->name('api.v1.returns.active');
    Route::get('/rentals/active', [ReturnController::class, 'activeRentals'])->name('api.v1.rentals.active');
    Route::get('/returns/summary', [ReturnController::class, 'summary'])->name('api.v1.returns.summary');
    Route::get('/returns/inspect/{bookingIdOrCode}', [ReturnController::class, 'inspectionPreview'])->name('api.v1.returns.inspection_preview');
    Route::get('/returns/{bookingIdOrCode}/inspection-preview', [ReturnController::class, 'inspectionPreview'])->name('api.v1.returns.inspection_preview_alias');
    Route::post('/returns/inspect/{bookingIdOrCode?}', [ReturnController::class, 'inspect'])->name('api.v1.returns.inspect');
    Route::post('/returns/inspection', [ReturnController::class, 'inspect'])->name('api.v1.returns.inspection');
    Route::post('/bookings/{idOrCode}/inspect', [ReturnController::class, 'inspect'])->name('api.v1.bookings.inspect');

    // Return Completion endpoints
    Route::post('/returns/complete/{bookingIdOrCode?}', [ReturnController::class, 'completeReturn'])->name('api.v1.returns.complete');
    Route::post('/returns/complete', [ReturnController::class, 'completeReturn'])->name('api.v1.returns.complete_post');
    Route::post('/bookings/{idOrCode}/return', [ReturnController::class, 'completeReturn'])->name('api.v1.bookings.return');

    // Affiliate & Partner Management endpoints
    Route::get('/affiliates', [AffiliateController::class, 'index'])->name('api.v1.affiliates.index');
    Route::post('/affiliates', [AffiliateController::class, 'store'])->name('api.v1.affiliates.store');
    Route::get('/affiliates/transfers', [AffiliateController::class, 'transfers'])->name('api.v1.affiliates.transfers');
    Route::post('/affiliates/transfers', [AffiliateController::class, 'storeTransfer'])->name('api.v1.affiliates.transfers.store');
    Route::post('/affiliates/transfers/{id}/accept', [AffiliateController::class, 'acceptTransfer'])->name('api.v1.affiliates.transfers.accept');
    Route::post('/affiliates/transfers/{id}/receive', [AffiliateController::class, 'acceptTransfer'])->name('api.v1.affiliates.transfers.receive');
    Route::get('/affiliates/{id}', [AffiliateController::class, 'show'])->name('api.v1.affiliates.show');
    Route::get('/affiliates/{id}/iphones', [AffiliateController::class, 'iphones'])->name('api.v1.affiliates.iphones');
    Route::get('/affiliates/{id}/bookings', [AffiliateController::class, 'bookings'])->name('api.v1.affiliates.bookings');
    Route::put('/affiliates/{id}', [AffiliateController::class, 'update'])->name('api.v1.affiliates.update');
    Route::delete('/affiliates/{id}', [AffiliateController::class, 'destroy'])->name('api.v1.affiliates.destroy');
    Route::get('/affiliates/{id}/revenue', [AffiliateController::class, 'revenue'])->name('api.v1.affiliates.revenue');
    Route::get('/affiliates/{id}/users', [AffiliateController::class, 'users'])->name('api.v1.affiliates.users');
    Route::get('/affiliates/{id}/available-users', [AffiliateController::class, 'availableUsers'])->name('api.v1.affiliates.available_users');
    Route::post('/affiliates/{id}/users', [AffiliateController::class, 'assignUsers'])->name('api.v1.affiliates.assign_users');
    Route::delete('/affiliates/{id}/users/{userId}', [AffiliateController::class, 'removeUser'])->name('api.v1.affiliates.remove_user');

    // Users, Roles & Permissions management endpoints (matches web admin/roles-permissions)
    Route::get('/users', [UserController::class, 'index'])->name('api.v1.users.index');
    Route::post('/users', [UserController::class, 'store'])->name('api.v1.users.store');
    Route::get('/roles', [UserController::class, 'roles'])->name('api.v1.roles.index');
    Route::get('/permissions', [UserController::class, 'permissions'])->name('api.v1.permissions.index');
    Route::post('/users/{id}/assign-role', [UserController::class, 'assignRole'])->name('api.v1.users.assign_role');
    Route::post('/users/{id}/assign-permissions', [UserController::class, 'assignRole'])->name('api.v1.users.assign_permissions');
    Route::post('/users/{id}/assign-role-permission', [UserController::class, 'assignRole'])->name('api.v1.users.assign_role_permission');
    Route::post('/users/{id}/roles', [UserController::class, 'assignRole']);
    Route::put('/users/{id}/roles', [UserController::class, 'assignRole']);
    Route::delete('/users/{id}', [UserController::class, 'destroy'])->name('api.v1.users.destroy');
});