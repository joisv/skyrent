<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\ShopSetting;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    /**
     * Handle user login and issue a Sanctum personal access token.
     */
    public function login(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => ['required_without_all:username,login', 'nullable', 'string'],
            'username' => ['nullable', 'string'],
            'login' => ['nullable', 'string'],
            'password' => ['required', 'string'],
            'device_name' => ['nullable', 'string'],
        ]);

        $identifier = $validated['email'] ?? $validated['username'] ?? $validated['login'] ?? null;

        if (!$identifier) {
            throw ValidationException::withMessages([
                'email' => ['Email atau nama pengguna wajib diisi.'],
            ]);
        }

        // Search user by email or name
        $user = User::where('email', $identifier)
            ->orWhere('name', $identifier)
            ->first();

        if (!$user || !Hash::check($validated['password'], $user->password)) {
            return response()->json([
                'success' => false,
                'message' => 'Email/nama pengguna atau kata sandi tidak valid. Silakan periksa kembali.',
                'errors' => [
                    'credentials' => ['Kredensial yang dimasukkan tidak cocok dengan catatan kami.'],
                ],
            ], 401);
        }

        $deviceName = $validated['device_name'] ?? 'SKYRental Admin Mobile';
        $token = $user->createToken($deviceName)->plainTextToken;

        $shop = ShopSetting::primary();

        $roles = method_exists($user, 'getRoleNames') ? $user->getRoleNames() : collect();

        $primaryRole = $roles->first() ?? 'staff';

        return response()->json([
            'success' => true,
            'message' => 'Login berhasil. Selamat datang di SKYRental Admin.',
            'data' => [
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'role' => $primaryRole,
                    'roles' => $roles,
                    'affiliate_id' => $user->affiliate_id,
                    'outlet_name' => $shop->outlet_name ?? 'Outlet Utama',
                    'shift_name' => 'Shift Aktif',
                ],
                'token' => $token,
                'token_type' => 'Bearer',
            ],
        ], 200);
    }

    /**
     * Handle user logout and revoke current Sanctum token.
     */
    public function logout(Request $request): JsonResponse
    {
        $user = $request->user();

        if ($user) {
            // Remove device token if provided in logout request
            $deviceToken = $request->input('device_token') ?? $request->input('fcm_token') ?? $request->input('token');
            if (!empty($deviceToken)) {
                \App\Models\DeviceToken::where('token', $deviceToken)
                    ->where('user_id', $user->id)
                    ->delete();
            }

            // Delete the current personal access token
            $request->user()->currentAccessToken()?->delete();
            auth()->guard('sanctum')->forgetUser();
        }

        return response()->json([
            'success' => true,
            'message' => 'Berhasil keluar dari akun (logout). Sesi telah ditutup.',
        ], 200);
    }

    /**
     * Get details of currently authenticated user.
     */
    public function me(Request $request): JsonResponse
    {
        $user = $request->user();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Tidak terautentikasi.',
            ], 401);
        }

        $shop = ShopSetting::primary();
        $roles = method_exists($user, 'getRoleNames') ? $user->getRoleNames() : collect();

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $roles->first() ?? 'Staff',
                'roles' => $roles,
                'affiliate_id' => $user->affiliate_id,
                'outlet_name' => $shop->outlet_name ?? 'Outlet Utama',
                'shift_name' => 'Shift Aktif',
            ],
        ], 200);
    }

    /**
     * Handle updating user password.
     */
    public function changePassword(Request $request): JsonResponse
    {
        $user = $request->user();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Tidak terautentikasi.',
            ], 401);
        }

        // Allow flexible naming: current_password / old_password, new_password / password
        $currentPassword = $request->input('current_password', $request->input('old_password'));
        $newPassword = $request->input('new_password', $request->input('password'));
        $newPasswordConfirmation = $request->input('new_password_confirmation', $request->input('password_confirmation'));

        $errors = [];

        if (empty($currentPassword)) {
            $errors['current_password'] = ['Kata sandi saat ini wajib diisi.'];
        }

        if (empty($newPassword)) {
            $errors['new_password'] = ['Kata sandi baru wajib diisi.'];
        } elseif (strlen($newPassword) < 6) {
            $errors['new_password'] = ['Kata sandi baru minimal harus 6 karakter.'];
        }

        if (!empty($newPasswordConfirmation) && $newPassword !== $newPasswordConfirmation) {
            $errors['new_password_confirmation'] = ['Konfirmasi kata sandi baru tidak cocok.'];
        }

        if (!empty($errors)) {
            throw ValidationException::withMessages($errors);
        }

        // Verify current password
        if (!Hash::check($currentPassword, $user->password)) {
            throw ValidationException::withMessages([
                'current_password' => ['Kata sandi saat ini yang Anda masukkan salah.'],
            ]);
        }

        // Verify new password is not identical to current password
        if (Hash::check($newPassword, $user->password)) {
            throw ValidationException::withMessages([
                'new_password' => ['Kata sandi baru tidak boleh sama dengan kata sandi saat ini.'],
            ]);
        }

        // Update password
        $user->forceFill([
            'password' => Hash::make($newPassword),
        ])->save();

        return response()->json([
            'success' => true,
            'message' => 'Kata sandi berhasil diperbarui. Silakan gunakan kata sandi baru untuk login berikutnya.',
        ], 200);
    }
}

