<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\IphoneResource;
use App\Models\Affiliate;
use App\Models\Booking;
use App\Models\BookingPayment;
use App\Models\Iphones;
use App\Models\IphoneTransfer;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class AffiliateController extends Controller
{
    /**
     * Resolve authenticated user from Sanctum request or bearer token.
     */
    protected function resolveUser(Request $request): ?User
    {
        $user = $request->user('sanctum') ?? $request->user();
        if (! $user && $bearer = $request->bearerToken()) {
            $tokenModel = \Laravel\Sanctum\PersonalAccessToken::findToken($bearer);
            if ($tokenModel && $tokenModel->tokenable instanceof User) {
                $user = $tokenModel->tokenable;
            }
        }
        return $user;
    }

    /**
     * Memeriksa apakah affiliate merupakan affiliate cabang pusat atau terhubung dengan super-admin.
     */
    protected function isPusatAffiliate(Affiliate $affiliate): bool
    {
        $hasSuperAdmin = $affiliate->relationLoaded('users')
            ? $affiliate->users->contains(function ($u) {
                return $u->relationLoaded('roles')
                    ? $u->roles->contains('name', 'super-admin')
                    : (method_exists($u, 'hasRole') && $u->hasRole('super-admin'));
            })
            : $affiliate->users()->whereHas('roles', fn($r) => $r->where('name', 'super-admin'))->exists();

        $nameCheck = Str::contains(strtolower($affiliate->code . ' ' . $affiliate->name . ' ' . $affiliate->slug), 'pusat');
        return $hasSuperAdmin || $nameCheck;
    }

    /**
     * Mendapatkan query iPhone untuk affiliate yang memperhitungkan cabang pusat (unit tanpa affiliate_id).
     */
    protected function getIphoneQueryForAffiliate(Affiliate $affiliate)
    {
        $query = Iphones::query();
        if ($this->isPusatAffiliate($affiliate)) {
            $query->where(function ($q) use ($affiliate) {
                $q->where('affiliate_id', $affiliate->id)->orWhereNull('affiliate_id');
            });
        } else {
            $query->where('affiliate_id', $affiliate->id);
        }
        return $query;
    }

    /**
     * Mendapatkan query booking untuk affiliate yang memperhitungkan cabang pusat.
     */
    protected function getBookingQueryForAffiliate(Affiliate $affiliate)
    {
        $query = Booking::query();
        $userIds = $affiliate->relationLoaded('users')
            ? $affiliate->users->pluck('id')->toArray()
            : $affiliate->users()->pluck('id')->toArray();

        if ($this->isPusatAffiliate($affiliate)) {
            $query->where(function ($q) use ($affiliate, $userIds) {
                $q->where('affiliate_id', $affiliate->id)
                    ->orWhere(function ($sub) use ($userIds) {
                        $sub->whereNull('affiliate_id');
                        if (!empty($userIds)) {
                            $sub->whereIn('user_id', $userIds);
                        }
                    });
            });
        } else {
            $query->where(function ($q) use ($affiliate) {
                $q->where('affiliate_id', $affiliate->id)
                    ->orWhere(function ($sub) use ($affiliate) {
                        $sub->whereNull('affiliate_id')
                            ->whereHas('iphone', fn($iq) => $iq->where('affiliate_id', $affiliate->id));
                    });
            });
        }
        return $query;
    }

    /**
     * Mengambil daftar mitra affiliate beserta statistik metrik.
     * GET /api/v1/affiliates
     */
    public function index(Request $request): JsonResponse
    {
        $query = Affiliate::query();

        if ($request->filled('search')) {
            $search = trim($request->input('search'));
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('code', 'like', "%{$search}%")
                    ->orWhere('city', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%");
            });
        }

        if ($request->has('is_active') && $request->input('is_active') !== '') {
            $query->where('is_active', filter_var($request->input('is_active'), FILTER_VALIDATE_BOOLEAN));
        }

        $affiliates = $query->with(['users.roles'])
            ->withCount(['iphones', 'bookings', 'users'])
            ->orderBy('name', 'asc')
            ->get();

        $today = Carbon::today('Asia/Jakarta');

        $data = $affiliates->map(function ($aff) use ($today) {
            $isPusat = $this->isPusatAffiliate($aff);

            if ($isPusat) {
                $bookingQuery = $this->getBookingQueryForAffiliate($aff);
                $bookingIds = (clone $bookingQuery)->pluck('id');

                $revenueToday = (float) BookingPayment::whereDate('paid_at', $today)
                    ->whereIn('booking_id', $bookingIds)
                    ->sum('amount');

                $totalRevenue = (float) BookingPayment::whereIn('booking_id', $bookingIds)
                    ->sum('amount');

                $iphonesCount = $this->getIphoneQueryForAffiliate($aff)->count();
                $bookingsCount = (clone $bookingQuery)->count();
            } else {
                $revenueToday = (float) BookingPayment::whereDate('paid_at', $today)
                    ->whereHas('booking', function ($q) use ($aff) {
                        $q->where('affiliate_id', $aff->id);
                    })
                    ->sum('amount');

                $totalRevenue = (float) BookingPayment::whereHas('booking', function ($q) use ($aff) {
                    $q->where('affiliate_id', $aff->id);
                })
                    ->sum('amount');

                $iphonesCount = (int) $aff->iphones_count;
                $bookingsCount = (int) $aff->bookings_count;
            }

            return [
                'id' => $aff->id,
                'code' => $aff->code,
                'name' => $aff->name,
                'slug' => $aff->slug,
                'email' => $aff->email,
                'phone' => $aff->phone,
                'address' => $aff->address,
                'city' => $aff->city,
                'province' => $aff->province,
                'postal_code' => $aff->postal_code,
                'latitude' => $aff->latitude ? (float) $aff->latitude : null,
                'longitude' => $aff->longitude ? (float) $aff->longitude : null,
                'logo' => $aff->logo,
                'description' => $aff->description,
                'is_active' => (bool) $aff->is_active,
                'iphones_count' => $iphonesCount,
                'bookings_count' => $bookingsCount,
                'users_count' => (int) $aff->users_count,
                'revenue_today' => $revenueToday,
                'total_revenue' => $totalRevenue,
                'created_at' => $aff->created_at?->toIso8601String(),
                'updated_at' => $aff->updated_at?->toIso8601String(),
            ];
        });

        $totalIphonesInAffiliates = Iphones::whereNotNull('affiliate_id')->count();
        $activeTransfersCount = IphoneTransfer::where('status', 'in_transit')->count();

        return response()->json([
            'success' => true,
            'message' => 'Daftar mitra affiliate berhasil dimuat.',
            'summary' => [
                'total_affiliates' => $affiliates->count(),
                'active_affiliates' => $affiliates->where('is_active', true)->count(),
                'total_iphones_deployed' => $totalIphonesInAffiliates,
                'active_transfers' => $activeTransfersCount,
            ],
            'data' => $data,
        ]);
    }

    /**
     * Membuat mitra affiliate baru.
     * POST /api/v1/affiliates
     */
    public function store(Request $request): JsonResponse
    {
        // Sanitize string inputs: convert empty strings to null
        $inputs = $request->all();
        foreach (['email', 'phone', 'address', 'city', 'province', 'postal_code', 'slug', 'description'] as $field) {
            if (isset($inputs[$field]) && is_string($inputs[$field]) && trim($inputs[$field]) === '') {
                $inputs[$field] = null;
            }
        }
        $request->merge($inputs);

        $validated = $request->validate([
            'code' => ['required', 'string', 'max:20', 'unique:affiliates,code'],
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255', 'unique:affiliates,slug'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:25'],
            'address' => ['nullable', 'string'],
            'city' => ['nullable', 'string', 'max:100'],
            'province' => ['nullable', 'string', 'max:100'],
            'postal_code' => ['nullable', 'string', 'max:10'],
            'latitude' => ['nullable', 'numeric'],
            'longitude' => ['nullable', 'numeric'],
            'description' => ['nullable', 'string'],
            'is_active' => ['nullable', 'boolean'],
        ], [
            'code.required' => 'Kode affiliate wajib diisi.',
            'code.unique' => 'Kode affiliate ini sudah terdaftar. Gunakan kode lain.',
            'code.max' => 'Kode affiliate maksimal 20 karakter.',
            'name.required' => 'Nama mitra affiliate wajib diisi.',
            'slug.unique' => 'Slug affiliate sudah digunakan.',
            'email.email' => 'Format email tidak valid.',
        ]);

        if (empty($validated['slug'])) {
            $validated['slug'] = Str::slug($validated['name']);
        } else {
            $validated['slug'] = Str::slug($validated['slug']);
        }

        // Ensure unique slug automatically
        $originalSlug = $validated['slug'];
        $count = 1;
        while (Affiliate::where('slug', $validated['slug'])->exists()) {
            $validated['slug'] = "{$originalSlug}-{$count}";
            $count++;
        }

        $validated['code'] = strtoupper(trim($validated['code']));
        $validated['is_active'] = $request->has('is_active')
            ? filter_var($request->input('is_active'), FILTER_VALIDATE_BOOLEAN)
            : true;

        $affiliate = Affiliate::create($validated);

        return response()->json([
            'success' => true,
            'message' => "Mitra affiliate '{$affiliate->name}' berhasil ditambahkan.",
            'data' => [
                'id' => $affiliate->id,
                'code' => $affiliate->code,
                'name' => $affiliate->name,
                'slug' => $affiliate->slug,
                'email' => $affiliate->email,
                'phone' => $affiliate->phone,
                'address' => $affiliate->address,
                'city' => $affiliate->city,
                'province' => $affiliate->province,
                'postal_code' => $affiliate->postal_code,
                'latitude' => $affiliate->latitude ? (float) $affiliate->latitude : null,
                'longitude' => $affiliate->longitude ? (float) $affiliate->longitude : null,
                'logo' => $affiliate->logo,
                'description' => $affiliate->description,
                'is_active' => (bool) $affiliate->is_active,
                'iphones_count' => 0,
                'bookings_count' => 0,
                'users_count' => 0,
                'revenue_today' => 0.0,
                'total_revenue' => 0.0,
                'created_at' => $affiliate->created_at?->toIso8601String(),
                'updated_at' => $affiliate->updated_at?->toIso8601String(),
            ],
        ], 201);
    }

    /**
     * Mengambil detail mitra affiliate beserta daftar iPhone, booking, dan user.
     * GET /api/v1/affiliates/{id}
     */
    public function show(string $id): JsonResponse
    {
        $affiliate = Affiliate::with([
            'users.roles',
        ])->find($id);

        if (!$affiliate) {
            return response()->json([
                'success' => false,
                'message' => "Data mitra affiliate dengan ID #{$id} tidak ditemukan.",
            ], 404);
        }

        $today = Carbon::today('Asia/Jakarta');

        $iphones = $this->getIphoneQueryForAffiliate($affiliate)
            ->with(['durations', 'gallery'])
            ->orderBy('name', 'asc')
            ->get();

        $bookingQuery = $this->getBookingQueryForAffiliate($affiliate);
        $bookingIds = (clone $bookingQuery)->pluck('id');

        $recentBookings = (clone $bookingQuery)
            ->with([
                'iphone.durations',
                'iphone.gallery',
                'user:id,name,email',
                'bookingPayments',
            ])
            ->latest('created_at')
            ->limit(30)
            ->get();

        $revenueToday = (float) BookingPayment::whereDate('paid_at', $today)
            ->whereIn('booking_id', $bookingIds)
            ->sum('amount');

        $totalRevenue = (float) BookingPayment::whereIn('booking_id', $bookingIds)
            ->sum('amount');

        $bookingsToday = (clone $bookingQuery)
            ->whereDate('created_at', $today)
            ->count();

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $affiliate->id,
                'code' => $affiliate->code,
                'name' => $affiliate->name,
                'slug' => $affiliate->slug,
                'email' => $affiliate->email,
                'phone' => $affiliate->phone,
                'address' => $affiliate->address,
                'city' => $affiliate->city,
                'province' => $affiliate->province,
                'postal_code' => $affiliate->postal_code,
                'latitude' => $affiliate->latitude ? (float) $affiliate->latitude : null,
                'longitude' => $affiliate->longitude ? (float) $affiliate->longitude : null,
                'logo' => $affiliate->logo,
                'description' => $affiliate->description,
                'is_active' => (bool) $affiliate->is_active,
                'kpi' => [
                    'iphones_count' => $iphones->count(),
                    'bookings_count' => (clone $bookingQuery)->count(),
                    'users_count' => $affiliate->users->count(),
                    'revenue_today' => $revenueToday,
                    'total_revenue' => $totalRevenue,
                    'bookings_today' => $bookingsToday,
                ],
                'users' => $affiliate->users,
                'iphones' => IphoneResource::collection($iphones),
                'recent_bookings' => $recentBookings,
                'created_at' => $affiliate->created_at?->toIso8601String(),
                'updated_at' => $affiliate->updated_at?->toIso8601String(),
            ],
        ]);
    }

    /**
     * Memperbarui data mitra affiliate.
     * PUT /api/v1/affiliates/{id}
     */
    public function update(Request $request, string $id): JsonResponse
    {
        $affiliate = Affiliate::find($id);
        if (!$affiliate) {
            return response()->json([
                'success' => false,
                'message' => "Mitra affiliate #{$id} tidak ditemukan.",
            ], 404);
        }

        // Sanitize string inputs: convert empty strings to null
        $inputs = $request->all();
        foreach (['email', 'phone', 'address', 'city', 'province', 'postal_code', 'slug', 'description'] as $field) {
            if (isset($inputs[$field]) && is_string($inputs[$field]) && trim($inputs[$field]) === '') {
                $inputs[$field] = null;
            }
        }
        $request->merge($inputs);

        $validated = $request->validate([
            'code' => ['sometimes', 'required', 'string', 'max:20', Rule::unique('affiliates', 'code')->ignore($affiliate->id)],
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255', Rule::unique('affiliates', 'slug')->ignore($affiliate->id)],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:25'],
            'address' => ['nullable', 'string'],
            'city' => ['nullable', 'string', 'max:100'],
            'province' => ['nullable', 'string', 'max:100'],
            'postal_code' => ['nullable', 'string', 'max:10'],
            'latitude' => ['nullable', 'numeric'],
            'longitude' => ['nullable', 'numeric'],
            'description' => ['nullable', 'string'],
            'is_active' => ['nullable', 'boolean'],
        ], [
            'code.required' => 'Kode affiliate wajib diisi.',
            'code.unique' => 'Kode affiliate ini sudah terdaftar. Gunakan kode lain.',
            'code.max' => 'Kode affiliate maksimal 20 karakter.',
            'name.required' => 'Nama mitra affiliate wajib diisi.',
            'slug.unique' => 'Slug affiliate sudah digunakan.',
            'email.email' => 'Format email tidak valid.',
        ]);

        if (isset($validated['code'])) {
            $validated['code'] = strtoupper(trim($validated['code']));
        }
        if (isset($validated['slug']) && empty($validated['slug']) && isset($validated['name'])) {
            $validated['slug'] = Str::slug($validated['name']);
        }
        if ($request->has('is_active')) {
            $validated['is_active'] = filter_var($request->input('is_active'), FILTER_VALIDATE_BOOLEAN);
        }

        $affiliate->update($validated);
        $fresh = $affiliate->fresh();

        return response()->json([
            'success' => true,
            'message' => "Data mitra affiliate '{$fresh->name}' berhasil diperbarui.",
            'data' => [
                'id' => $fresh->id,
                'code' => $fresh->code,
                'name' => $fresh->name,
                'slug' => $fresh->slug,
                'email' => $fresh->email,
                'phone' => $fresh->phone,
                'address' => $fresh->address,
                'city' => $fresh->city,
                'province' => $fresh->province,
                'postal_code' => $fresh->postal_code,
                'latitude' => $fresh->latitude ? (float) $fresh->latitude : null,
                'longitude' => $fresh->longitude ? (float) $fresh->longitude : null,
                'logo' => $fresh->logo,
                'description' => $fresh->description,
                'is_active' => (bool) $fresh->is_active,
                'iphones_count' => (int) $fresh->iphones()->count(),
                'bookings_count' => (int) $fresh->bookings()->count(),
                'users_count' => (int) $fresh->users()->count(),
                'revenue_today' => 0.0,
                'total_revenue' => 0.0,
                'created_at' => $fresh->created_at?->toIso8601String(),
                'updated_at' => $fresh->updated_at?->toIso8601String(),
            ],
        ]);
    }

    /**
     * Menghapus mitra affiliate.
     * DELETE /api/v1/affiliates/{id}
     */
    public function destroy(string $id): JsonResponse
    {
        $affiliate = Affiliate::find($id);
        if (!$affiliate) {
            return response()->json([
                'success' => false,
                'message' => "Mitra affiliate #{$id} tidak ditemukan.",
            ], 404);
        }

        // Cek jika ada unit iphone atau booking aktif
        $activeBookings = Booking::where('affiliate_id', $affiliate->id)
            ->whereIn('status', ['confirmed', 'rented'])
            ->count();

        if ($activeBookings > 0) {
            return response()->json([
                'success' => false,
                'message' => "Affiliate tidak dapat dihapus karena masih memiliki {$activeBookings} booking yang sedang aktif.",
            ], 422);
        }

        // Unlink iphones dan users
        Iphones::where('affiliate_id', $affiliate->id)->update(['affiliate_id' => null]);
        \App\Models\User::where('affiliate_id', $affiliate->id)->update(['affiliate_id' => null]);

        $affiliate->delete();

        return response()->json([
            'success' => true,
            'message' => "Mitra affiliate '{$affiliate->name}' berhasil dihapus.",
        ]);
    }

    /**
     * Mengambil daftar riwayat transfer / mutasi iPhone.
     * GET /api/v1/affiliates/transfers
     */
    public function transfers(Request $request): JsonResponse
    {
        $user = $this->resolveUser($request);

        $query = IphoneTransfer::with([
            'iphone:id,name,serial_number,status,asset_code,affiliate_id',
            'fromAffiliate:id,code,name,city',
            'toAffiliate:id,code,name,city',
            'sender:id,name,email',
            'receiver:id,name,email',
        ]);

        $isSuperAdmin = $user && method_exists($user, 'hasRole') && $user->hasRole('super-admin');
        $isAdmin = $user && method_exists($user, 'hasRole') && $user->hasRole('admin');
        $isAffiliateAdmin = $user && method_exists($user, 'hasRole') && $user->hasRole('affiliate-admin');
        $isAffiliate = $user && method_exists($user, 'hasRole') && $user->hasRole('affiliate');

        // Otorisasi: hanya super-admin, admin, affiliate-admin, dan affiliate yang diizinkan
        if (!$isSuperAdmin && !$isAdmin && !$isAffiliateAdmin && !$isAffiliate) {
            return response()->json([
                'success' => false,
                'message' => 'Akses ditolak: Anda tidak memiliki izin untuk melihat daftar transfer iPhone.',
            ], 403);
        }

        if ($isAffiliateAdmin || $isAffiliate) {
            if ($user->affiliate_id) {
                // Scope affiliate / affiliate-admin: HANYA menampilkan transfer yang ditujukan ke affiliate miliknya
                // Mengabaikan parameter affiliate_id dari client untuk mencegah cross-affiliate leakage
                $query->where('to_affiliate_id', $user->affiliate_id);
            } else {
                $query->whereRaw('1 = 0');
            }
        } elseif ($isSuperAdmin) {
            if ($request->filled('affiliate_id')) {
                $affId = $request->input('affiliate_id');
                $type = $request->input('type'); // 'inbound' or 'outbound'
                if ($type === 'inbound') {
                    $query->where('to_affiliate_id', $affId);
                } elseif ($type === 'outbound') {
                    $query->where('from_affiliate_id', $affId);
                } else {
                    $query->where(function ($q) use ($affId) {
                        $q->where('from_affiliate_id', $affId)
                            ->orWhere('to_affiliate_id', $affId);
                    });
                }
            }
        } elseif ($isAdmin) {
            if ($user->affiliate_id) {
                $query->where('to_affiliate_id', $user->affiliate_id);
            } elseif ($request->filled('affiliate_id')) {
                $affId = $request->input('affiliate_id');
                $query->where(function ($q) use ($affId) {
                    $q->where('from_affiliate_id', $affId)
                        ->orWhere('to_affiliate_id', $affId);
                });
            }
        }

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        $transfers = $query->latest('sent_at')->get();

        $data = $transfers->map(function ($tr) {
            return [
                'id' => $tr->id,
                'iphone_id' => $tr->iphone_id,
                'iphone_name' => $tr->iphone?->name ?? 'iPhone',
                'iphone_serial' => $tr->iphone?->serial_number ?? '-',
                'iphone_color' => $tr->iphone?->color ?? '',
                'from_affiliate_id' => $tr->from_affiliate_id,
                'from_affiliate_name' => $tr->fromAffiliate?->name ?? 'Pusat (SkyRent)',
                'from_affiliate_code' => $tr->fromAffiliate?->code ?? 'PST',
                'to_affiliate_id' => $tr->to_affiliate_id,
                'to_affiliate_name' => $tr->toAffiliate?->name ?? 'Tujuan',
                'to_affiliate_code' => $tr->toAffiliate?->code ?? '-',
                'sent_by' => $tr->sent_by,
                'sender_name' => $tr->sender?->name ?? 'Admin',
                'received_by' => $tr->received_by,
                'receiver_name' => $tr->receiver?->name,
                'status' => $tr->status, // 'in_transit', 'received', 'pending'
                'notes' => $tr->notes,
                'sent_at' => $tr->sent_at,
                'received_at' => $tr->received_at,
                'created_at' => $tr->created_at?->toIso8601String(),
            ];
        });

        return response()->json([
            'success' => true,
            'message' => 'Daftar transfer iPhone berhasil dimuat.',
            'data' => $data,
        ]);
    }

    /**
     * Mengirim iPhone ke cabang affiliate lain (membuat transfer baru).
     * POST /api/v1/affiliates/transfers
     */
    public function storeTransfer(Request $request): JsonResponse
    {
        $user = $this->resolveUser($request);

        $validated = $request->validate([
            'iphone_id' => ['required', 'exists:iphones,id'],
            'to_affiliate_id' => ['required', 'exists:affiliates,id'],
            'from_affiliate_id' => ['nullable', 'exists:affiliates,id'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        $iphone = Iphones::findOrFail($validated['iphone_id']);

        // 1. Cek apakah unit iPhone sedang dalam proses mutasi aktif (in_transit / pending)
        $existingTransfer = IphoneTransfer::where('iphone_id', $iphone->id)
            ->whereIn('status', ['in_transit', 'pending'])
            ->with('toAffiliate')
            ->first();

        if ($existingTransfer) {
            $destName = $existingTransfer->toAffiliate?->name ?? 'cabang tujuan';
            return response()->json([
                'success' => false,
                'message' => "Unit {$iphone->name} saat ini sedang dalam perjalanan mutasi/pengiriman aktif ke {$destName} dan belum diterima.",
            ], 409);
        }

        // 2. Cek status ketersediaan unit iPhone
        $rawStatus = strtolower($iphone->status ?? 'ready');
        if (in_array($rawStatus, ['rented', 'disewa'])) {
            return response()->json([
                'success' => false,
                'message' => "Unit {$iphone->name} sedang disewa dan tidak dapat dimutasi/ditransfer.",
            ], 422);
        }

        if (in_array($rawStatus, ['transferred', 'in_transit'])) {
            return response()->json([
                'success' => false,
                'message' => "Unit {$iphone->name} sudah berstatus mutasi/pengiriman dan belum diterima di cabang tujuan.",
            ], 409);
        }

        if (in_array($rawStatus, ['maintenance', 'perawatan'])) {
            return response()->json([
                'success' => false,
                'message' => "Unit {$iphone->name} sedang dalam masa perawatan (maintenance) dan tidak dapat dimutasi.",
            ], 422);
        }

        $fromAffiliateId = $validated['from_affiliate_id'] ?? $iphone->affiliate_id ?? $user?->affiliate_id;
        if (!$fromAffiliateId) {
            $fromAffiliateId = Affiliate::where('is_active', true)
                ->where('id', '!=', $validated['to_affiliate_id'])
                ->orderBy('id')
                ->value('id') ?? 4;
        }

        if ($fromAffiliateId == $validated['to_affiliate_id']) {
            return response()->json([
                'success' => false,
                'message' => 'Affiliate tujuan tidak boleh sama dengan affiliate asal.',
            ], 422);
        }

        return DB::transaction(function () use ($iphone, $validated, $fromAffiliateId, $user) {
            // Lock row unit untuk mencegah race condition atau double submit simultan
            $lockedIphone = Iphones::where('id', $iphone->id)->lockForUpdate()->first();

            $concurrencyCheck = IphoneTransfer::where('iphone_id', $lockedIphone->id)
                ->whereIn('status', ['in_transit', 'pending'])
                ->lockForUpdate()
                ->first();

            if ($concurrencyCheck) {
                return response()->json([
                    'success' => false,
                    'message' => "Unit {$lockedIphone->name} sudah dalam proses transfer aktif ke cabang tujuan.",
                ], 409);
            }

            $transfer = IphoneTransfer::create([
                'iphone_id' => $lockedIphone->id,
                'from_affiliate_id' => $fromAffiliateId,
                'to_affiliate_id' => $validated['to_affiliate_id'],
                'sent_by' => $user?->id ?? auth()->id() ?? 1,
                'status' => 'in_transit',
                'notes' => $validated['notes'] ?? null,
                'sent_at' => now(),
            ]);

            $lockedIphone->update([
                'status' => 'transferred',
            ]);

            $transfer->load(['iphone', 'fromAffiliate', 'toAffiliate', 'sender']);

            return response()->json([
                'success' => true,
                'message' => "iPhone {$lockedIphone->name} berhasil dikirim ke {$transfer->toAffiliate?->name}.",
                'data' => $transfer,
            ], 201);
        });
    }

    /**
     * Menerima unit iPhone yang dikirim (mengonfirmasi transfer).
     * POST /api/v1/affiliates/transfers/{id}/accept
     */
    public function acceptTransfer(Request $request, string $id): JsonResponse
    {
        $user = $this->resolveUser($request);

        $transfer = IphoneTransfer::with(['iphone', 'toAffiliate'])->find($id);

        if (!$transfer) {
            return response()->json([
                'success' => false,
                'message' => "Data transfer #{$id} tidak ditemukan.",
            ], 404);
        }

        $isSuperAdmin = $user && method_exists($user, 'hasRole') && $user->hasRole('super-admin');
        $isAdmin = $user && method_exists($user, 'hasRole') && $user->hasRole('admin');
        $isAffiliateAdmin = $user && method_exists($user, 'hasRole') && $user->hasRole('affiliate-admin');
        $isAffiliate = $user && method_exists($user, 'hasRole') && $user->hasRole('affiliate');

        if (!$isSuperAdmin && !$isAdmin && !$isAffiliateAdmin && !$isAffiliate) {
            return response()->json([
                'success' => false,
                'message' => 'Anda tidak memiliki hak akses untuk menerima transfer iPhone ini.',
            ], 403);
        }

        // Cek otorisasi untuk role affiliate & affiliate-admin: hanya boleh menerima transfer yang ditujukan ke cabangnya
        if ($isAffiliateAdmin || $isAffiliate || ($isAdmin && $user?->affiliate_id)) {
            if (!$user?->affiliate_id || (int) $transfer->to_affiliate_id !== (int) $user->affiliate_id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Anda tidak memiliki hak akses untuk menerima transfer iPhone ini.',
                ], 403);
            }
        }

        if ($transfer->status === 'received') {
            return response()->json([
                'success' => false,
                'message' => 'iPhone pada transfer ini sudah diterima sebelumnya.',
            ], 422);
        }

        if (!in_array($transfer->status, ['in_transit', 'pending'])) {
            return response()->json([
                'success' => false,
                'message' => "Transfer tidak dapat diterima karena saat ini berstatus '{$transfer->status}'.",
            ], 422);
        }

        if (!$transfer->iphone) {
            return response()->json([
                'success' => false,
                'message' => 'Data unit iPhone untuk transfer ini tidak ditemukan.',
            ], 422);
        }

        return DB::transaction(function () use ($transfer, $user) {
            $receiverId = $user?->id ?? auth()->id() ?? 1;

            $transfer->update([
                'status' => 'received',
                'received_by' => $receiverId,
                'received_at' => now(),
            ]);

            // Bersihkan / sinkronkan jika ada transfer duplikat in_transit untuk iPhone yang sama
            IphoneTransfer::where('iphone_id', $transfer->iphone_id)
                ->where('id', '!=', $transfer->id)
                ->whereIn('status', ['in_transit', 'pending'])
                ->update([
                    'status' => 'received',
                    'received_by' => $receiverId,
                    'received_at' => now(),
                    'notes' => DB::raw("CONCAT(COALESCE(notes, ''), ' (Auto-resolved duplicate transfer)')"),
                ]);

            if ($transfer->iphone) {
                $transfer->iphone->update([
                    'affiliate_id' => $transfer->to_affiliate_id,
                    'status' => 'ready',
                ]);
            }

            return response()->json([
                'success' => true,
                'message' => "iPhone {$transfer->iphone?->name} berhasil diterima dan siap disewakan di {$transfer->toAffiliate?->name}.",
                'data' => $transfer->fresh(['iphone', 'toAffiliate', 'receiver']),
            ]);
        });
    }

    /**
     * Rekap pendapatan dan riwayat pembayaran affiliate dengan filter rentang tanggal.
     * GET /api/v1/affiliates/{id}/revenue
     */
    public function revenue(Request $request, string $id): JsonResponse
    {
        $user = $this->resolveUser($request);

        $isSuperAdmin = $user && method_exists($user, 'hasRole') && $user->hasRole('super-admin');
        $isAdmin = $user && method_exists($user, 'hasRole') && $user->hasRole('admin');
        $isAffiliateAdmin = $user && method_exists($user, 'hasRole') && $user->hasRole('affiliate-admin');
        $isAffiliate = $user && method_exists($user, 'hasRole') && $user->hasRole('affiliate');

        // Affiliate and affiliate-admin must only see their own affiliate revenue
        if ($isAffiliateAdmin || $isAffiliate) {
            if (!$user->affiliate_id || (int) $id !== (int) $user->affiliate_id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Akses ditolak: Anda tidak memiliki izin untuk melihat laporan pendapatan affiliate ini.',
                ], 403);
            }
        } elseif ($isAdmin && $user->affiliate_id) {
            if ((int) $id !== (int) $user->affiliate_id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Akses ditolak: Anda tidak memiliki izin untuk melihat laporan pendapatan affiliate ini.',
                ], 403);
            }
        }

        $affiliate = Affiliate::findOrFail($id);

        $startDate = $request->input('start_date', now()->subDays(6)->toDateString());
        $endDate = $request->input('end_date', now()->toDateString());

        $start = Carbon::parse($startDate)->startOfDay();
        $end = Carbon::parse($endDate)->endOfDay();

        $isPusat = $this->isPusatAffiliate($affiliate);
        $bookingQuery = $this->getBookingQueryForAffiliate($affiliate);
        $bookingIds = (clone $bookingQuery)->pluck('id');

        if ($isPusat) {
            $query = BookingPayment::query()
                ->whereBetween('paid_at', [$start, $end])
                ->whereIn('booking_id', $bookingIds);
        } else {
            $query = BookingPayment::query()
                ->whereBetween('paid_at', [$start, $end])
                ->whereHas('booking', function ($q) use ($affiliate) {
                    $q->where(function ($sub) use ($affiliate) {
                        $sub->where('affiliate_id', $affiliate->id)
                            ->orWhere(function ($legacy) use ($affiliate) {
                                $legacy->whereNull('affiliate_id')
                                    ->whereHas('iphone', fn($iq) => $iq->where('affiliate_id', $affiliate->id));
                            });
                    });
                });
        }

        $payments = $query->with([
            'booking.iphone',
            'user:id,name,email',
        ])
            ->latest('paid_at')
            ->get();

        $today = Carbon::today('Asia/Jakarta');

        if ($isPusat) {
            $revenueToday = (float) BookingPayment::whereDate('paid_at', $today)
                ->whereIn('booking_id', $bookingIds)
                ->sum('amount');

            $bookingToday = (clone $bookingQuery)
                ->whereDate('created_at', $today)
                ->count();
        } else {
            $revenueToday = (float) BookingPayment::whereDate('paid_at', $today)
                ->whereHas('booking', function ($q) use ($affiliate) {
                    $q->where(function ($sub) use ($affiliate) {
                        $sub->where('affiliate_id', $affiliate->id)
                            ->orWhere(function ($legacy) use ($affiliate) {
                                $legacy->whereNull('affiliate_id')
                                    ->whereHas('iphone', fn($iq) => $iq->where('affiliate_id', $affiliate->id));
                            });
                    });
                })
                ->sum('amount');

            $bookingToday = (clone $bookingQuery)
                ->whereDate('created_at', $today)
                ->count();
        }

        $affiliateRevenue = (float) $payments->sum('amount');
        $affiliateBookingCount = $payments->pluck('booking_id')->unique()->count();

        $paymentList = $payments->map(function ($p) {
            return [
                'id' => $p->id,
                'booking_id' => $p->booking_id,
                'booking_code' => $p->booking?->booking_code ?? '-',
                'customer_name' => $p->booking?->customer_name ?? 'Pelanggan',
                'iphone_name' => $p->booking?->iphone?->name ?? 'iPhone',
                'amount' => (float) $p->amount,
                'payment_method' => $p->payment_method,
                'type' => $p->type,
                'paid_at' => $p->paid_at,
                'staff_name' => $p->user?->name ?? 'Admin',
            ];
        });

        return response()->json([
            'success' => true,
            'message' => "Laporan pendapatan untuk {$affiliate->name} berhasil dimuat.",
            'data' => [
                'affiliate_id' => $affiliate->id,
                'affiliate_name' => $affiliate->name,
                'affiliate_code' => $affiliate->code,
                'start_date' => $startDate,
                'end_date' => $endDate,
                'affiliate_revenue' => $affiliateRevenue,
                'affiliate_booking_count' => $affiliateBookingCount,
                'revenue_today' => $revenueToday,
                'booking_today' => $bookingToday,
                'payments_count' => $payments->count(),
                'payments' => $paymentList,
            ],
        ]);
    }

    /**
     * Mengambil daftar user/staf yang ditugaskan ke mitra affiliate.
     * GET /api/v1/affiliates/{id}/users
     */
    public function users(string $id): JsonResponse
    {
        $affiliate = Affiliate::findOrFail($id);

        $users = User::where('affiliate_id', $affiliate->id)
            ->with('roles')
            ->orderBy('name', 'asc')
            ->get();

        $data = $users->map(function ($u) {
            return [
                'id' => (string) $u->id,
                'name' => $u->name,
                'email' => $u->email,
                'phone' => $u->phone,
                'role' => $u->roles->pluck('name')->first() ?? 'Staff',
                'affiliate_id' => $u->affiliate_id,
                'is_assigned' => true,
                'created_at' => $u->created_at?->toIso8601String(),
            ];
        });

        return response()->json([
            'success' => true,
            'message' => "Daftar pengguna untuk {$affiliate->name} berhasil dimuat.",
            'data' => $data,
        ]);
    }

    /**
     * Mengambil daftar seluruh user untuk dipilih/ditugaskan ke mitra affiliate.
     * GET /api/v1/affiliates/{id}/available-users
     */
    public function availableUsers(Request $request, string $id): JsonResponse
    {
        $affiliate = Affiliate::findOrFail($id);

        $query = User::with(['roles', 'affiliate:id,code,name']);

        if ($request->filled('search')) {
            $search = trim($request->input('search'));
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            });
        }

        $users = $query->orderBy('name', 'asc')->get();

        $data = $users->map(function ($u) use ($affiliate) {
            return [
                'id' => (string) $u->id,
                'name' => $u->name,
                'email' => $u->email,
                'phone' => $u->phone,
                'role' => $u->roles->pluck('name')->first() ?? 'Staff',
                'affiliate_id' => $u->affiliate_id,
                'is_assigned' => (int) $u->affiliate_id === (int) $affiliate->id,
                'current_affiliate_name' => $u->affiliate?->name,
            ];
        });

        return response()->json([
            'success' => true,
            'message' => 'Daftar pengguna yang tersedia berhasil dimuat.',
            'data' => $data,
        ]);
    }

    /**
     * Menugaskan satu atau lebih user ke mitra affiliate.
     * POST /api/v1/affiliates/{id}/users
     */
    public function assignUsers(Request $request, string $id): JsonResponse
    {
        $affiliate = Affiliate::findOrFail($id);

        $validated = $request->validate([
            'user_ids' => ['required', 'array', 'min:1'],
            'user_ids.*' => ['required', 'string', 'exists:users,id'],
        ]);

        User::whereIn('id', $validated['user_ids'])->update([
            'affiliate_id' => $affiliate->id,
        ]);

        $assignedUsers = User::where('affiliate_id', $affiliate->id)->with('roles')->get();

        $count = count($validated['user_ids']);

        return response()->json([
            'success' => true,
            'message' => "{$count} user berhasil ditambahkan ke affiliate {$affiliate->name}.",
            'data' => $assignedUsers->map(function ($u) {
                return [
                    'id' => (string) $u->id,
                    'name' => $u->name,
                    'email' => $u->email,
                    'phone' => $u->phone,
                    'role' => $u->roles->pluck('name')->first() ?? 'Staff',
                    'affiliate_id' => $u->affiliate_id,
                    'is_assigned' => true,
                ];
            }),
        ]);
    }

    /**
     * Melepas penugasan user dari mitra affiliate.
     * DELETE /api/v1/affiliates/{id}/users/{userId}
     */
    public function removeUser(string $id, string $userId): JsonResponse
    {
        $affiliate = Affiliate::findOrFail($id);

        $user = User::where('id', $userId)
            ->where('affiliate_id', $affiliate->id)
            ->first();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => "User tidak terhubung dengan cabang {$affiliate->name}.",
            ], 404);
        }

        $user->update(['affiliate_id' => null]);

        return response()->json([
            'success' => true,
            'message' => "User {$user->name} berhasil dilepas dari cabang {$affiliate->name}.",
        ]);
    }

    /**
     * Mengambil daftar unit iPhone milik mitra affiliate (termasuk unit pusat jika cabang pusat).
     * GET /api/v1/affiliates/{id}/iphones
     */
    public function iphones(string $id): JsonResponse
    {
        $affiliate = Affiliate::findOrFail($id);
        $iphones = $this->getIphoneQueryForAffiliate($affiliate)
            ->with(['durations', 'gallery'])
            ->orderBy('name', 'asc')
            ->get();

        return response()->json([
            'success' => true,
            'message' => "Daftar unit iPhone untuk {$affiliate->name} berhasil dimuat.",
            'data' => IphoneResource::collection($iphones),
        ]);
    }

    /**
     * Mengambil riwayat / daftar booking milik mitra affiliate (termasuk booking pusat jika cabang pusat).
     * GET /api/v1/affiliates/{id}/bookings
     */
    public function bookings(Request $request, string $id): JsonResponse
    {
        $affiliate = Affiliate::findOrFail($id);
        $query = $this->getBookingQueryForAffiliate($affiliate)
            ->with([
                'iphone.durations',
                'iphone.gallery',
                'user:id,name,email',
                'bookingPayments',
            ]);

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        $bookings = $query->latest('created_at')->limit(50)->get();

        return response()->json([
            'success' => true,
            'message' => "Daftar booking untuk {$affiliate->name} berhasil dimuat.",
            'data' => $bookings,
        ]);
    }
}

