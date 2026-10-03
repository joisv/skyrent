<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use App\Models\ShopSetting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SettingController extends Controller
{
    /**
     * Get the primary shop and outlet configuration.
     */
    public function getShopSettings(): JsonResponse
    {
        $shop = ShopSetting::primary();

        return response()->json([
            'success' => true,
            'message' => 'Pengaturan toko berhasil dimuat.',
            'data' => [
                'shop_name' => $shop->shop_name ?? 'SKYRental iPhone POS',
                'outlet_name' => $shop->outlet_name ?? 'Outlet Utama Malioboro',
                'address' => $shop->address ?? 'Jl. Malioboro No. 45, Danurejan, D.I. Yogyakarta',
                'phone_number' => $shop->phone_number ?? '0812-3456-7890',
                'footer_note' => $shop->footer_note ?? 'Terima kasih telah mempercayakan sewa iPhone kepada SKYRental',
                'wifi_name' => $shop->wifi_name ?? 'SKYRENTAL_GUEST',
                'wifi_password' => $shop->wifi_password ?? 'rentaliphoneoke',
            ],
        ], 200);
    }

    /**
     * Update primary shop and outlet configuration.
     */
    public function updateShopSettings(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'shop_name' => ['required', 'string', 'max:255'],
            'outlet_name' => ['required', 'string', 'max:255'],
            'address' => ['required', 'string', 'max:500'],
            'phone_number' => ['required', 'string', 'max:50'],
            'footer_note' => ['nullable', 'string', 'max:500'],
            'wifi_name' => ['nullable', 'string', 'max:100'],
            'wifi_password' => ['nullable', 'string', 'max:100'],
        ]);

        $shop = ShopSetting::updatePrimary($validated);

        // Keep key-value store in sync as well
        foreach ($validated as $key => $value) {
            Setting::updateOrCreate(
                ['key' => $key],
                ['value' => (string) $value, 'group' => 'shop']
            );
        }

        return response()->json([
            'success' => true,
            'message' => 'Pengaturan toko berhasil diperbarui.',
            'data' => [
                'shop_name' => $shop->shop_name,
                'outlet_name' => $shop->outlet_name,
                'address' => $shop->address,
                'phone_number' => $shop->phone_number,
                'footer_note' => $shop->footer_note,
                'wifi_name' => $shop->wifi_name,
                'wifi_password' => $shop->wifi_password,
            ],
        ], 200);
    }
}