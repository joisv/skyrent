<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\BookingResource;
use App\Http\Resources\IphoneResource;
use App\Models\Booking;
use App\Models\Duration;
use App\Models\Gallery;
use App\Models\Iphones;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class IphoneController extends Controller
{
    /**
     * Display a listing of iPhone units with optional filtering.
     * GET /api/v1/iphones
     * GET /api/v1/iphones/unit-status
     */
    private function resolveUser(Request $request): ?\App\Models\User
    {
        $user = $request->user('sanctum') ?? $request->user();
        if (! $user && $bearer = $request->bearerToken()) {
            $tokenModel = \Laravel\Sanctum\PersonalAccessToken::findToken($bearer);
            if ($tokenModel && $tokenModel->tokenable instanceof \App\Models\User) {
                $user = $tokenModel->tokenable;
            }
        }
        return $user;
    }

    /**
     * Determine real-time rental status ('disewa', 'terlambat', or 'tersedia')
     * without loading entire historical booking relationships.
     */
    private function resolveUnitRealtimeStatus(Iphones $unit, Carbon $now): array
    {
        $status = 'tersedia';
        $relevantBooking = null;

        $bookings = $unit->relationLoaded('bookings')
            ? $unit->bookings
            : $unit->bookings()
                ->whereIn('status', ['confirmed', 'rented', 'disewa'])
                ->whereDoesntHave('returns')
                ->orderBy('end_booking_date', 'desc')
                ->orderBy('end_time', 'desc')
                ->get();

        foreach ($bookings as $b) {
            $startTime = $b->start_time ? substr($b->start_time, 0, 5) : '00:00';
            $endTime = $b->end_time ? substr($b->end_time, 0, 5) : '23:59';
            $startDt = Carbon::parse($b->start_booking_date . ' ' . $startTime, 'Asia/Jakarta');
            $endDt = Carbon::parse($b->end_booking_date . ' ' . $endTime, 'Asia/Jakarta');

            if ($now->greaterThan($endDt)) {
                $status = 'terlambat';
                $relevantBooking = $b;
                break;
            } elseif ($now->greaterThanOrEqualTo($startDt) && $now->lessThanOrEqualTo($endDt)) {
                $status = 'disewa';
                $relevantBooking = $b;
                break;
            }
        }

        if ($status === 'tersedia') {
            $rawStatus = strtolower($unit->status ?? 'ready');
            if (in_array($rawStatus, ['maintenance', 'perawatan'])) {
                $status = 'maintenance';
            }
        }

        return [
            'status' => $status,
            'booking' => $relevantBooking,
        ];
    }

    /**
     * Helper to compute fleet status counts.
     * Guaranteed: total = tersedia + disewa + terlambat (no double counting).
     */
    private function calculateUnitSummary(?int $affiliateId = null): array
    {
        $now = Carbon::now('Asia/Jakarta');
        $query = Iphones::query();
        if ($affiliateId) {
            $query->where('affiliate_id', $affiliateId);
        }

        $allUnits = $query->with(['bookings' => function ($bq) {
            $bq->whereIn('status', ['confirmed', 'rented', 'disewa'])
               ->whereDoesntHave('returns')
               ->orderBy('end_booking_date', 'desc')
               ->orderBy('end_time', 'desc');
        }])->get();

        $total = $allUnits->count();
        $tersedia = 0;
        $disewa = 0;
        $terlambat = 0;

        foreach ($allUnits as $unit) {
            $res = $this->resolveUnitRealtimeStatus($unit, $now);
            if ($res['status'] === 'terlambat') {
                $terlambat++;
            } elseif ($res['status'] === 'disewa') {
                $disewa++;
            } else {
                $tersedia++;
            }
        }

        return [
            'total' => $total,
            'tersedia' => $tersedia,
            'disewa' => $disewa,
            'terlambat' => $terlambat,
            'maintenance' => 0,
            'dibooking' => 0,
            // Standard English aliases
            'ready' => $tersedia,
            'rented' => $disewa,
            'overdue' => $terlambat,
            'available' => $tersedia,
        ];
    }

    /**
     * Display a listing of iPhone units with optional filtering.
     * GET /api/v1/iphones
     * GET /api/v1/iphones/unit-status
     */
    public function index(Request $request): JsonResponse
    {
        $now = Carbon::now('Asia/Jakarta');

        // Affiliate scoping check based on authenticated user or request
        $user = $this->resolveUser($request);
        $isAffiliateAdmin = $user && method_exists($user, 'hasRole') && $user->hasRole('affiliate-admin');
        $isAdmin = $user && method_exists($user, 'hasRole') && $user->hasRole('admin');

        $affiliateId = null;
        if ($isAffiliateAdmin) {
            $affiliateId = $user->affiliate_id;
        } elseif ($isAdmin && $user->affiliate_id) {
            $affiliateId = $user->affiliate_id;
        } else {
            $affiliateId = $request->query('affiliate_id');
        }

        $query = Iphones::with([
            'gallery',
            'affiliate',
            'durations',
            'bookings' => function ($bq) {
                $bq->whereIn('status', ['confirmed', 'rented', 'disewa'])
                   ->whereDoesntHave('returns')
                   ->orderBy('end_booking_date', 'desc')
                   ->orderBy('end_time', 'desc');
            }
        ]);

        if ($isAffiliateAdmin) {
            if ($affiliateId) {
                $query->where('affiliate_id', $affiliateId);
            } else {
                $query->whereRaw('1 = 0');
            }
        } elseif ($affiliateId) {
            $query->where('affiliate_id', $affiliateId);
        } elseif ($branch = $request->query('branch') ?? $request->query('affiliate') ?? $request->query('branch_name')) {
            $b = trim($branch);
            if (strtolower($b) !== 'semua cabang' && strtolower($b) !== 'semua' && strtolower($b) !== 'all') {
                $query->whereHas('affiliate', function ($q) use ($b) {
                    $q->where('name', 'like', "%{$b}%");
                });
            }
        }

        // Filter by model name (e.g. "iPhone 15 Pro")
        if ($model = $request->query('model') ?? $request->query('model_name')) {
            $query->where('name', 'like', "%{$model}%");
        }

        // Filter by search term (name, serial number, asset code, description)
        if ($search = $request->query('q') ?? $request->query('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('serial_number', 'like', "%{$search}%")
                    ->orWhere('asset_code', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%");
            });
        }

        // Sorting
        $sortBy = $request->query('sort_by', 'name');
        $sortDir = strtolower($request->query('sort_dir', 'asc')) === 'desc' ? 'desc' : 'asc';
        $allowedSorts = ['name', 'asset_code', 'status', 'created_at'];

        if (in_array($sortBy, $allowedSorts)) {
            $query->orderBy($sortBy, $sortDir);
        } else {
            $query->orderBy('name')->orderBy('asset_code');
        }

        $allUnits = $query->get();

        // Calculate dynamic real-time status for each unit
        foreach ($allUnits as $unit) {
            $resolved = $this->resolveUnitRealtimeStatus($unit, $now);
            $unit->realtime_status = $resolved['status'];
            $unit->realtime_booking = $resolved['booking'];
        }

        // Filter by status if provided (support Indonesian and English)
        $statusFilter = $request->query('status');
        if ($statusFilter && !in_array(strtolower($statusFilter), ['all', 'semua'])) {
            $sf = strtolower(trim($statusFilter));
            $allUnits = $allUnits->filter(function ($unit) use ($sf) {
                $st = strtolower($unit->realtime_status ?? 'tersedia');
                if (in_array($sf, ['ready', 'tersedia'])) {
                    return $st === 'tersedia';
                } elseif (in_array($sf, ['rented', 'disewa'])) {
                    return $st === 'disewa';
                } elseif (in_array($sf, ['terlambat', 'overdue', 'late'])) {
                    return $st === 'terlambat';
                } elseif (in_array($sf, ['maintenance', 'perawatan'])) {
                    return $st === 'maintenance';
                }
                return $st === $sf;
            })->values();
        }

        // Filter available only
        if ($request->boolean('available_only') || $request->query('only_available') === '1') {
            $allUnits = $allUnits->filter(fn($u) => ($u->realtime_status ?? 'tersedia') === 'tersedia')->values();
        }

        $summary = $this->calculateUnitSummary($affiliateId);

        // Optional pagination
        if ($request->has('per_page') || $request->boolean('paginate')) {
            $perPage = min(100, max(1, (int) $request->query('per_page', 15)));
            $page = max(1, (int) $request->query('page', 1));
            $totalCount = $allUnits->count();
            $pagedItems = $allUnits->forPage($page, $perPage)->values();

            return response()->json([
                'status' => 'success',
                'summary' => $summary,
                'data' => IphoneResource::collection($pagedItems),
                'meta' => [
                    'current_page' => $page,
                    'per_page' => $perPage,
                    'total' => $totalCount,
                    'last_page' => (int) ceil($totalCount / $perPage),
                ],
            ]);
        }

        return response()->json([
            'status' => 'success',
            'total' => $allUnits->count(),
            'summary' => $summary,
            'data' => IphoneResource::collection($allUnits),
        ]);
    }

    /**
     * Display a summary of iPhone unit statuses for operational dashboards.
     * GET /api/v1/iphones/summary
     * GET /api/v1/units/summary
     */
    public function summary(Request $request): JsonResponse
    {
        $user = $this->resolveUser($request);
        $isAffiliateAdmin = $user && method_exists($user, 'hasRole') && $user->hasRole('affiliate-admin');
        $isAdmin = $user && method_exists($user, 'hasRole') && $user->hasRole('admin');

        $affiliateId = null;
        if ($isAffiliateAdmin) {
            $affiliateId = $user->affiliate_id ?? -1;
        } elseif ($isAdmin && $user->affiliate_id) {
            $affiliateId = $user->affiliate_id;
        } else {
            $affiliateId = $request->query('affiliate_id');
        }

        return response()->json([
            'status' => 'success',
            'data' => $this->calculateUnitSummary($affiliateId),
        ]);
    }

    /**
     * Display a listing of available iPhone units ready for pickup assignment.
     * GET /api/v1/iphones/available
     * GET /api/v1/pickup/available-units
     */
    public function available(Request $request): JsonResponse
    {
        $now = Carbon::now('Asia/Jakarta');

        $user = $this->resolveUser($request);
        $isAffiliateAdmin = $user && method_exists($user, 'hasRole') && $user->hasRole('affiliate-admin');
        $isAdmin = $user && method_exists($user, 'hasRole') && $user->hasRole('admin');

        $affiliateId = null;
        if ($isAffiliateAdmin) {
            $affiliateId = $user->affiliate_id;
        } elseif ($isAdmin && $user->affiliate_id) {
            $affiliateId = $user->affiliate_id;
        } else {
            $affiliateId = $request->query('affiliate_id');
        }

        $query = Iphones::with([
            'gallery',
            'affiliate',
            'durations',
            'bookings' => function ($bq) {
                $bq->whereIn('status', ['confirmed', 'rented', 'disewa'])
                   ->whereDoesntHave('returns')
                   ->orderBy('end_booking_date', 'desc')
                   ->orderBy('end_time', 'desc');
            }
        ]);

        if ($isAffiliateAdmin) {
            if ($affiliateId) {
                $query->where('affiliate_id', $affiliateId);
            } else {
                $query->whereRaw('1 = 0');
            }
        } elseif ($affiliateId) {
            $query->where('affiliate_id', $affiliateId);
        }

        // If booking_code or booking_id is provided, match its model if not explicitly specified
        if ($bookingCode = $request->query('booking_code') ?? $request->query('booking_id')) {
            $booking = Booking::with('iphone')
                ->where(function ($q) use ($bookingCode) {
                    if (is_numeric($bookingCode)) {
                        $q->where('id', (int) $bookingCode)
                            ->orWhere('booking_code', $bookingCode);
                    } else {
                        $q->where('booking_code', $bookingCode);
                    }
                })
                ->first();

            if ($booking && $booking->iphone) {
                if (! $request->has('model') && ! $request->has('model_name')) {
                    $query->where('name', 'like', "%{$booking->iphone->name}%");
                }
            }
        }

        // Filter by model name if provided
        if ($model = $request->query('model') ?? $request->query('model_name')) {
            $query->where('name', 'like', "%{$model}%");
        }

        // Filter by search query (name, serial number, asset code)
        if ($search = $request->query('q') ?? $request->query('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('serial_number', 'like', "%{$search}%")
                    ->orWhere('asset_code', 'like', "%{$search}%");
            });
        }

        $units = $query->orderBy('name')->orderBy('asset_code')->get();

        $availableUnits = $units->filter(function ($unit) use ($now) {
            $resolved = $this->resolveUnitRealtimeStatus($unit, $now);
            $unit->realtime_status = $resolved['status'];
            $unit->realtime_booking = $resolved['booking'];
            return $resolved['status'] === 'tersedia';
        })->values();

        return response()->json([
            'status' => 'success',
            'total_available' => $availableUnits->count(),
            'data' => IphoneResource::collection($availableUnits),
        ]);
    }

    /**
     * Display the specified iPhone unit.
     * GET /api/v1/iphones/{idOrAssetCode}
     */
    public function show(Request $request, string $idOrAssetCode): JsonResponse
    {
        $user = $this->resolveUser($request);
        $isAffiliateAdmin = $user && $user->hasRole('affiliate-admin');

        $unitQuery = Iphones::with([
            'gallery',
            'affiliate',
            'durations',
            'bookings' => function ($bq) {
                $bq->whereIn('status', ['confirmed', 'rented', 'disewa'])
                   ->whereDoesntHave('returns')
                   ->orderBy('end_booking_date', 'desc')
                   ->orderBy('end_time', 'desc');
            }
        ])->where(function ($q) use ($idOrAssetCode) {
            if (is_numeric($idOrAssetCode)) {
                $q->where('id', (int) $idOrAssetCode)
                    ->orWhere('asset_code', $idOrAssetCode)
                    ->orWhere('serial_number', $idOrAssetCode);
            } else {
                $q->where('asset_code', $idOrAssetCode)
                    ->orWhere('serial_number', $idOrAssetCode);
            }
        });

        if ($isAffiliateAdmin && $user->affiliate_id) {
            $unitQuery->where('affiliate_id', $user->affiliate_id);
        }

        $unit = $unitQuery->first();

        if (! $unit) {
            return response()->json([
                'status' => 'error',
                'message' => "Unit iPhone dengan kode aset atau ID '{$idOrAssetCode}' tidak ditemukan.",
            ], 404);
        }

        $resolved = $this->resolveUnitRealtimeStatus($unit, Carbon::now('Asia/Jakarta'));
        $unit->realtime_status = $resolved['status'];
        $unit->realtime_booking = $resolved['booking'];

        return response()->json([
            'status' => 'success',
            'data' => new IphoneResource($unit),
        ]);
    }

    /**
     * Display rental schedule and timeline for a specific iPhone unit.
     * GET /api/v1/iphones/{idOrAssetCode}/schedule
     * GET /api/v1/units/{idOrAssetCode}/schedule
     */
    public function schedule(Request $request, string $idOrAssetCode): JsonResponse
    {
        $unit = Iphones::with(['gallery', 'affiliate', 'activeBooking', 'currentRental'])
            ->where(function ($q) use ($idOrAssetCode) {
                if (is_numeric($idOrAssetCode)) {
                    $q->where('id', (int) $idOrAssetCode)
                        ->orWhere('asset_code', $idOrAssetCode)
                        ->orWhere('serial_number', $idOrAssetCode);
                } else {
                    $q->where('asset_code', $idOrAssetCode)
                        ->orWhere('serial_number', $idOrAssetCode);
                }
            })
            ->first();

        if (! $unit) {
            return response()->json([
                'status' => 'error',
                'message' => "Unit iPhone dengan kode aset atau ID '{$idOrAssetCode}' tidak ditemukan.",
            ], 404);
        }

        $today = Carbon::today('Asia/Jakarta')->toDateString();

        // Fetch all bookings for this unit
        $allBookings = Booking::with(['payment', 'user', 'iphone'])
            ->where('iphone_id', $unit->id)
            ->orderBy('start_booking_date', 'asc')
            ->get();

        $activeBookings = $allBookings->filter(fn($b) => in_array($b->status, ['rented', 'disewa']));
        $upcomingBookings = $allBookings->filter(fn($b) => in_array($b->status, ['confirmed', 'pending']) && $b->start_booking_date >= $today);
        $completedBookings = $allBookings->filter(fn($b) => in_array($b->status, ['returned', 'completed', 'cancelled']));

        // Currently active booking
        $currentActive = $activeBookings->sortByDesc('id')->first();

        // Calculate next available date
        $nextAvailableDate = $today;
        if ($currentActive && $currentActive->end_booking_date >= $today) {
            $nextAvailableDate = Carbon::parse($currentActive->end_booking_date)->addDay()->toDateString();
        }

        // Apply timeframe filter for the 'data' timeline collection
        $timeframe = strtolower(trim($request->query('timeframe', $request->query('filter', 'all'))));
        $timeline = match ($timeframe) {
            'active', 'aktif' => $activeBookings->values(),
            'upcoming', 'mendatang' => $upcomingBookings->values(),
            'past', 'selesai', 'history' => $completedBookings->sortByDesc('start_booking_date')->values(),
            default => $allBookings->sortByDesc('start_booking_date')->values(),
        };

        return response()->json([
            'status' => 'success',
            'unit' => new IphoneResource($unit),
            'summary' => [
                'total' => $allBookings->count(),
                'aktif' => $activeBookings->count(),
                'mendatang' => $upcomingBookings->count(),
                'selesai' => $completedBookings->count(),
                // Standard English aliases
                'active' => $activeBookings->count(),
                'upcoming' => $upcomingBookings->count(),
                'completed' => $completedBookings->count(),
                'next_available_date' => $nextAvailableDate,
                'is_currently_rented' => $activeBookings->isNotEmpty(),
            ],
            'current_rental' => $currentActive ? new BookingResource($currentActive) : null,
            'upcoming_bookings' => BookingResource::collection($upcomingBookings->values()),
            'rental_history' => BookingResource::collection($completedBookings->sortByDesc('start_booking_date')->values()),
            'data' => BookingResource::collection($timeline),
        ]);
    }

    /**
     * Create a new iPhone unit with duration pricing packages matching Livewire/Iphones/Create.php.
     * POST /api/v1/iphones
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:2000',
            'gallery_id' => 'nullable|integer',
            'slug' => 'nullable|string|max:255',
            'serial_number' => 'required|string|max:255',
            'asset_code' => 'required|string|max:255|unique:iphones,asset_code',
            'status' => 'nullable|string|max:50',
            'affiliate_id' => 'nullable|integer',
            'created' => 'nullable|string',
            'durations' => 'nullable|array',
        ], [
            'name.required' => 'Nama model iPhone wajib diisi.',
            'serial_number.required' => 'Nomor seri (SN) iPhone wajib diisi.',
            'asset_code.required' => 'Kode aset unit iPhone wajib diisi.',
            'asset_code.unique' => 'Kode aset :input sudah terdaftar pada unit iPhone lain.',
        ]);

        $slug = !empty($validated['slug']) ? Str::slug($validated['slug']) : Str::slug($validated['name']);
        $originalSlug = $slug;
        $count = 2;
        while (Iphones::where('slug', $slug)->exists()) {
            $slug = $originalSlug . '-' . $count;
            $count++;
        }

        $userId = auth()->id() ?? \App\Models\User::first()?->id ?? Iphones::first()?->user_id;

        $createdDate = isset($validated['created']) && !empty($validated['created'])
            ? Carbon::parse($validated['created'])->toDateString()
            : Carbon::now()->toDateString();

        $galleryId = $validated['gallery_id'] ?? null;
        if (! $galleryId || ! Gallery::where('id', $galleryId)->exists()) {
            $galleryId = Gallery::first()?->id ?? 1;
        }

        $affiliateId = !empty($validated['affiliate_id']) && is_numeric($validated['affiliate_id'])
            ? (int) $validated['affiliate_id']
            : null;

        $iphone = Iphones::create([
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'gallery_id' => $galleryId,
            'user_id' => $userId,
            'slug' => $slug,
            'created' => $createdDate,
            'serial_number' => $validated['serial_number'],
            'asset_code' => $validated['asset_code'],
            'status' => $validated['status'] ?? 'ready',
            'affiliate_id' => $affiliateId,
        ]);

        // Attach duration packages if provided (matching Livewire/Iphones/Create.php:61-78)
        if (!empty($validated['durations']) && is_array($validated['durations'])) {
            $syncData = [];
            foreach ($validated['durations'] as $item) {
                $hours = isset($item['hours']) ? (int) $item['hours'] : 24;
                if ($hours <= 0) continue;

                $duration = Duration::firstOrCreate(['hours' => $hours]);

                $rawPrice = $item['price'] ?? 100000;
                $cleanPrice = (int) preg_replace('/[^\d]/', '', (string) $rawPrice);

                $syncData[$duration->id] = ['price' => $cleanPrice];
            }

            if (!empty($syncData)) {
                $iphone->durations()->attach($syncData);
            }
        }

        return response()->json([
            'status' => 'success',
            'message' => "Unit iPhone {$iphone->name} ({$iphone->asset_code}) berhasil ditambahkan!",
            'data' => new IphoneResource($iphone->load(['gallery', 'affiliate', 'durations'])),
        ], 201);
    }

    /**
     * Update an existing iPhone unit with duration pricing packages matching Livewire/Iphones/Edit.php.
     * PUT /api/v1/iphones/{idOrAssetCode}
     */
    public function update(Request $request, string $idOrAssetCode): JsonResponse
    {
        $iphone = Iphones::where(function ($q) use ($idOrAssetCode) {
            if (is_numeric($idOrAssetCode)) {
                $q->where('id', (int) $idOrAssetCode)
                    ->orWhere('asset_code', $idOrAssetCode)
                    ->orWhere('serial_number', $idOrAssetCode);
            } else {
                $q->where('asset_code', $idOrAssetCode)
                    ->orWhere('serial_number', $idOrAssetCode);
            }
        })->first();

        if (! $iphone) {
            return response()->json([
                'status' => 'error',
                'message' => "Unit iPhone dengan kode aset atau ID '{$idOrAssetCode}' tidak ditemukan.",
            ], 404);
        }

        $user = $this->resolveUser($request);
        if ($user && method_exists($user, 'hasRole') && $user->hasRole('affiliate-admin')) {
            if ($user->affiliate_id && $iphone->affiliate_id != $user->affiliate_id) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Anda tidak memiliki hak akses untuk mengubah unit dari affiliate lain.',
                ], 403);
            }
        }

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:2000',
            'gallery_id' => 'nullable|integer',
            'slug' => 'nullable|string|max:255|unique:iphones,slug,' . $iphone->id,
            'serial_number' => 'required|string|max:255',
            'asset_code' => 'required|string|max:255|unique:iphones,asset_code,' . $iphone->id,
            'status' => 'nullable|string|max:50',
            'affiliate_id' => 'nullable|integer',
            'created' => 'nullable|string',
            'durations' => 'nullable|array',
        ], [
            'name.required' => 'Nama model iPhone wajib diisi.',
            'serial_number.required' => 'Nomor seri (SN) iPhone wajib diisi.',
            'asset_code.required' => 'Kode aset unit iPhone wajib diisi.',
            'asset_code.unique' => 'Kode aset :input sudah terdaftar pada unit iPhone lain.',
            'slug.unique' => 'Slug :input sudah digunakan oleh unit iPhone lain.',
        ]);

        $slug = !empty($validated['slug']) ? Str::slug($validated['slug']) : Str::slug($validated['name']);
        $originalSlug = $slug;
        $count = 2;
        while (Iphones::where('slug', $slug)->where('id', '!=', $iphone->id)->exists()) {
            $slug = $originalSlug . '-' . $count;
            $count++;
        }

        $galleryId = $validated['gallery_id'] ?? $iphone->gallery_id;
        if (! $galleryId || ! Gallery::where('id', $galleryId)->exists()) {
            $galleryId = $iphone->gallery_id ?? Gallery::first()?->id ?? 1;
        }

        $affiliateId = array_key_exists('affiliate_id', $validated)
            ? (!empty($validated['affiliate_id']) && is_numeric($validated['affiliate_id']) ? (int) $validated['affiliate_id'] : null)
            : $iphone->affiliate_id;

        $createdDate = isset($validated['created']) && !empty($validated['created'])
            ? Carbon::parse($validated['created'])->toDateString()
            : $iphone->created;

        $status = $iphone->status;
        if (isset($validated['status']) && !empty($validated['status'])) {
            $s = strtolower(trim($validated['status']));
            $status = match ($s) {
                'tersedia' => 'ready',
                'disewa' => 'rented',
                'perawatan' => 'maintenance',
                'dibooking' => 'booked',
                default => $s,
            };
        }

        $iphone->update([
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'gallery_id' => $galleryId,
            'slug' => $slug,
            'created' => $createdDate,
            'serial_number' => $validated['serial_number'],
            'asset_code' => $validated['asset_code'],
            'status' => $status,
            'affiliate_id' => $affiliateId,
        ]);

        // Sync duration packages if provided (matching Livewire/Iphones/Edit.php:46-60)
        if (isset($validated['durations']) && is_array($validated['durations'])) {
            $syncData = [];
            foreach ($validated['durations'] as $item) {
                $hours = isset($item['hours']) ? (int) $item['hours'] : 24;
                if ($hours <= 0) continue;

                $duration = Duration::firstOrCreate(['hours' => $hours]);

                $rawPrice = $item['price'] ?? 100000;
                $cleanPrice = (int) preg_replace('/[^\d]/', '', (string) $rawPrice);

                $syncData[$duration->id] = ['price' => $cleanPrice];
            }

            $iphone->durations()->sync($syncData);
        }

        return response()->json([
            'status' => 'success',
            'message' => "Unit iPhone {$iphone->name} ({$iphone->asset_code}) berhasil diperbarui!",
            'data' => new IphoneResource($iphone->fresh(['gallery', 'affiliate', 'durations'])),
        ]);
    }

    /**
     * Update status or notes for an iPhone unit.
     * POST/PUT /api/v1/iphones/{idOrAssetCode}/status
     */
    public function updateStatus(Request $request, string $idOrAssetCode): JsonResponse
    {
        $unit = Iphones::where(function ($q) use ($idOrAssetCode) {
            if (is_numeric($idOrAssetCode)) {
                $q->where('id', (int) $idOrAssetCode)
                    ->orWhere('asset_code', $idOrAssetCode)
                    ->orWhere('serial_number', $idOrAssetCode);
            } else {
                $q->where('asset_code', $idOrAssetCode)
                    ->orWhere('serial_number', $idOrAssetCode);
            }
        })->first();

        if (! $unit) {
            return response()->json([
                'status' => 'error',
                'message' => "Unit iPhone dengan ID atau kode '{$idOrAssetCode}' tidak ditemukan.",
            ], 404);
        }

        $user = $this->resolveUser($request);
        if ($user && method_exists($user, 'hasRole') && $user->hasRole('affiliate-admin')) {
            if ($user->affiliate_id && $unit->affiliate_id != $user->affiliate_id) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Anda tidak memiliki hak akses untuk mengubah status unit dari affiliate lain.',
                ], 403);
            }
        }

        $validated = $request->validate([
            'status' => 'nullable|string',
            'notes' => 'nullable|string',
            'battery_health' => 'nullable|integer',
        ]);

        if (isset($validated['status']) && ! empty($validated['status'])) {
            $s = strtolower(trim($validated['status']));
            $unit->status = match ($s) {
                'tersedia' => 'ready',
                'disewa' => 'rented',
                'perawatan' => 'maintenance',
                'dibooking' => 'booked',
                default => $s,
            };
        }

        if (isset($validated['notes'])) {
            $unit->notes = $validated['notes'];
        }

        $unit->save();

        return response()->json([
            'status' => 'success',
            'message' => "Status unit iPhone {$unit->asset_code} berhasil diperbarui.",
            'data' => new IphoneResource($unit->fresh()),
        ]);
    }

    /**
     * Get list of gallery posters.
     * GET /api/v1/galleries
     */
    public function galleries(): JsonResponse
    {
        $galleries = Gallery::select('id', 'image')->orderBy('id', 'desc')->get()->map(function ($g) {
            return [
                'id' => $g->id,
                'image' => $g->image,
                'url' => filter_var($g->image, FILTER_VALIDATE_URL) ? $g->image : asset('storage/' . $g->image),
            ];
        });
        return response()->json([
            'status' => 'success',
            'data' => $galleries,
        ]);
    }

    /**
     * Upload an image to gallery from client internal storage.
     * POST /api/v1/galleries/upload
     * POST /api/v1/galleries
     */
    public function uploadGallery(Request $request): JsonResponse
    {
        $request->validate([
            'image' => 'required|file|mimes:jpeg,png,jpg,gif,webp,heic|max:10240',
        ]);

        $file = $request->file('image');
        $path = $file->store('galleries', 'public');

        $gallery = Gallery::create([
            'image' => $path,
        ]);

        $url = asset('storage/' . $path);

        return response()->json([
            'status' => 'success',
            'message' => 'Gambar berhasil diupload ke galeri.',
            'data' => [
                'id' => $gallery->id,
                'image' => $gallery->image,
                'url' => $url,
            ],
        ], 201);
    }
}

