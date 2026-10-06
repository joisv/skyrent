<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\BookingResource;
use App\Models\Booking;
use App\Models\BookingPayment;
use App\Models\Iphones;
use App\Models\Payment;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class BookingController extends Controller
{
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

    protected function applyUserScope($query, ?User $user)
    {
        if (! $user) {
            return $query;
        }

        // super-admin: melihat semua booking dari semua user dan semua affiliate
        if (method_exists($user, 'hasRole') && $user->hasRole('super-admin')) {
            return $query;
        }

        // admin: dapat melihat semua booking yang menjadi cakupan admin (termasuk user lain & affiliate)
        if (method_exists($user, 'hasRole') && $user->hasRole('admin')) {
            if ($user->affiliate_id) {
                return $query->where(function ($q) use ($user) {
                    $q->where('affiliate_id', $user->affiliate_id)
                        ->orWhere('user_id', $user->id);
                });
            }
            // Admin sistem umum: dapat melihat semua booking yang diizinkan sistem
            return $query;
        }

        // affiliate-admin / affiliate / affiliate-scoped users: hanya melihat booking dari affiliasi miliknya atau booking buatannya sendiri
        if (method_exists($user, 'hasRole') && ($user->hasRole('affiliate-admin') || $user->hasRole('affiliate') || (!empty($user->affiliate_id) && !$user->hasRole('super-admin')))) {
            if ($user->affiliate_id) {
                return $query->where(function ($q) use ($user) {
                    $q->where('affiliate_id', $user->affiliate_id)
                        ->orWhere('user_id', $user->id);
                });
            }
            return $query->where('user_id', $user->id);
        }

        // Role lainnya (staff, kasir, dll): hanya boleh melihat booking yang dibuat oleh dirinya sendiri
        return $query->where('user_id', $user->id);
    }

    protected function canAccessBooking(Booking $booking, ?User $user): bool
    {
        if (! $user) {
            return true;
        }

        if (method_exists($user, 'hasRole') && $user->hasRole('super-admin')) {
            return true;
        }

        if (method_exists($user, 'hasRole') && $user->hasRole('admin')) {
            if ($user->affiliate_id) {
                return $booking->affiliate_id == $user->affiliate_id || $booking->user_id == $user->id;
            }
            return true;
        }

        if (method_exists($user, 'hasRole') && ($user->hasRole('affiliate-admin') || $user->hasRole('affiliate') || (!empty($user->affiliate_id) && !$user->hasRole('super-admin')))) {
            if ($user->affiliate_id) {
                return $booking->affiliate_id == $user->affiliate_id || $booking->user_id == $user->id;
            }
            return $booking->user_id == $user->id;
        }

        // Role lainnya (staff): hanya boleh mengakses booking miliknya sendiri
        return $booking->user_id == $user->id;
    }

    /**
     * Display a listing of bookings with multi-field search and filters.
     * GET /api/v1/bookings
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $query = Booking::query()->with([
            'iphone.durations',
            'iphone.gallery',
            'payment',
            'user',
            'latestReturn',
            'paymentTransactions',
        ]);

        // Role-based authorization scoping
        $user = $this->resolveUser($request);
        $this->applyUserScope($query, $user);

        // Multi-field search
        if ($search = $request->query('search', $request->query('q'))) {
            $search = trim($search);
            $query->search([
                'customer_name',
                'customer_phone',
                'customer_email',
                'booking_code',
                'status',
                'iphone.serial_number',
                'iphone.name',
                'user.name',
            ], $search);
        }

        // Filter by booking status
        if ($status = $request->query('status')) {
            if ($status !== 'all' && $status !== 'semua') {
                $query->where('status', $status);
            }
        }

        // Filter by payment status
        if ($paymentStatus = $request->query('payment_status')) {
            if ($paymentStatus !== 'all' && $paymentStatus !== 'semua') {
                $query->where('payment_status', $paymentStatus);
            }
        }

        // Filter by pickup type
        if ($pickupType = $request->query('pickup_type')) {
            if ($pickupType !== 'all' && $pickupType !== 'semua') {
                $query->where('pickup_type', $pickupType);
            }
        }

        // Filter by today only
        if ($request->boolean('today_only') || $request->query('only_today') === '1') {
            $today = Carbon::today()->toDateString();
            $query->where(function ($q) use ($today) {
                $q->whereDate('start_booking_date', $today)
                    ->orWhereDate('end_booking_date', $today)
                    ->orWhereDate('created_at', $today);
            });
        }

        // Sorting
        $sortBy = $request->query('sort_by', 'created_at');
        $sortDirection = strtolower($request->query('sort_direction', 'desc')) === 'asc' ? 'asc' : 'desc';
        $allowedSorts = ['created_at', 'start_booking_date', 'end_booking_date', 'price', 'duration'];

        if (in_array($sortBy, $allowedSorts)) {
            $query->orderBy($sortBy, $sortDirection);
        } else {
            $query->orderBy('created_at', 'desc');
        }

        $perPage = min((int) $request->query('per_page', 15), 100);

        return BookingResource::collection($query->paginate($perPage));
    }

    /**
     * Dedicated fast search endpoint for booking lookup by query.
     * GET /api/v1/bookings/search?q={query}
     */
    public function search(Request $request): JsonResponse
    {
        $term = trim($request->query('q', $request->query('query', $request->query('search', ''))));

        if (empty($term)) {
            return response()->json([
                'status' => 'success',
                'query' => '',
                'total' => 0,
                'data' => [],
            ]);
        }

        $query = Booking::query()->with([
            'iphone.gallery',
            'payment',
            'user',
        ]);

        // Role-based authorization scoping
        $user = $this->resolveUser($request);
        $this->applyUserScope($query, $user);

        $cleanPhone = preg_replace('/[^0-9]/', '', $term);
        if (str_starts_with($cleanPhone, '62')) {
            $cleanPhone = '0' . substr($cleanPhone, 2);
        }

        $query->where(function ($q) use ($term, $cleanPhone) {
            $q->where('booking_code', 'like', "%{$term}%")
                ->orWhere('customer_name', 'like', "%{$term}%")
                ->orWhere('customer_email', 'like', "%{$term}%")
                ->orWhere('customer_phone', 'like', "%{$term}%")
                ->orWhereHas('iphone', function ($iq) use ($term) {
                    $iq->where('name', 'like', "%{$term}%")
                        ->orWhere('serial_number', 'like', "%{$term}%")
                        ->orWhere('asset_code', 'like', "%{$term}%");
                });

            if (!empty($cleanPhone) && $cleanPhone !== $term) {
                $q->orWhere('customer_phone', 'like', "%{$cleanPhone}%");
            }
        });

        // Filter status if provided
        if ($status = $request->query('status')) {
            if ($status !== 'all' && $status !== 'semua') {
                $query->where('status', $status);
            }
        }

        // Rank exact booking code matches first
        $query->orderByRaw("CASE WHEN booking_code = ? THEN 0 WHEN booking_code LIKE ? THEN 1 ELSE 2 END", [$term, "{$term}%"])
            ->orderBy('created_at', 'desc');

        $limit = min((int) $request->query('limit', 20), 50);
        $results = $query->limit($limit)->get();

        return response()->json([
            'status' => 'success',
            'query' => $term,
            'total' => $results->count(),
            'data' => BookingResource::collection($results),
        ]);
    }

    /**
     * Display today's operational bookings (pickups, returns, and newly created).
     * GET /api/v1/bookings/today
     */
    public function today(Request $request): JsonResponse
    {
        $today = Carbon::today()->toDateString();
        $user = $this->resolveUser($request);

        // 1. Pickups scheduled for today (confirmed status ready for pickup)
        $pickupsQuery = Booking::with(['iphone.gallery', 'payment'])
            ->whereDate('start_booking_date', $today)
            ->where('status', 'confirmed');
        $this->applyUserScope($pickupsQuery, $user);
        $pickupsToday = $pickupsQuery->orderBy('start_time', 'asc')->get();

        // 2. Returns scheduled for today or overdue
        $returnsQuery = Booking::with(['iphone.gallery', 'payment'])
            ->whereDate('end_booking_date', '<=', $today)
            ->whereIn('status', ['confirmed', 'rented']);
        $this->applyUserScope($returnsQuery, $user);
        $returnsToday = $returnsQuery->orderBy('end_time', 'asc')->get();

        // 3. Newly created bookings today
        $createdQuery = Booking::with(['iphone.gallery', 'payment'])
            ->whereDate('created_at', $today);
        $this->applyUserScope($createdQuery, $user);
        $createdToday = $createdQuery->orderBy('created_at', 'desc')->get();

        return response()->json([
            'status' => 'success',
            'date' => $today,
            'summary' => [
                'total_pickups_today' => $pickupsToday->count(),
                'total_returns_today' => $returnsToday->count(),
                'total_created_today' => $createdToday->count(),
            ],
            'data' => [
                'pickups' => BookingResource::collection($pickupsToday),
                'returns' => BookingResource::collection($returnsToday),
                'created_today' => BookingResource::collection($createdToday),
            ],
        ]);
    }

    /**
     * Display the specified booking by ID or booking code.
     * GET /api/v1/bookings/{idOrCode}
     */
    public function show(Request $request, string $idOrCode): JsonResponse
    {
        $booking = Booking::with([
            'iphone.durations',
            'iphone.gallery',
            'payment',
            'bookingPayments',
            'returns',
            'latestReturn',
            'revenue',
            'user',
        ])
        ->where(function ($q) use ($idOrCode) {
            if (is_numeric($idOrCode)) {
                $q->where('id', (int) $idOrCode)
                    ->orWhere('booking_code', $idOrCode);
            } else {
                $q->where('booking_code', $idOrCode);
            }
        })
        ->first();

        if (! $booking) {
            return response()->json([
                'status' => 'error',
                'message' => "Booking dengan kode atau ID '{$idOrCode}' tidak ditemukan.",
            ], 404);
        }

        $user = $this->resolveUser($request);
        if (! $this->canAccessBooking($booking, $user)) {
            return response()->json([
                'status' => 'error',
                'message' => 'Anda tidak memiliki hak akses untuk melihat booking ini.',
            ], 403);
        }

        return response()->json([
            'status' => 'success',
            'data' => new BookingResource($booking),
        ]);
    }

    /**
     * Display booking detail with verification checklist and pickup readiness.
     * GET /api/v1/bookings/{idOrCode}/verify
     * GET /api/v1/pickup/verify/{idOrCode}
     */
    public function verifyDetail(Request $request, string $idOrCode): JsonResponse
    {
        $booking = Booking::with([
            'iphone.gallery',
            'payment',
            'bookingPayments',
            'returns',
            'latestReturn',
            'revenue',
            'user',
        ])
        ->where(function ($q) use ($idOrCode) {
            if (is_numeric($idOrCode)) {
                $q->where('id', (int) $idOrCode)
                    ->orWhere('booking_code', $idOrCode);
            } else {
                $q->where('booking_code', $idOrCode);
            }
        })
        ->first();

        if (! $booking) {
            return response()->json([
                'status' => 'error',
                'message' => "Booking dengan kode atau ID '{$idOrCode}' tidak ditemukan.",
            ], 404);
        }

        $user = $this->resolveUser($request);
        if (! $this->canAccessBooking($booking, $user)) {
            return response()->json([
                'status' => 'error',
                'message' => 'Anda tidak memiliki hak akses untuk memverifikasi booking ini.',
            ], 403);
        }

        $canPickup = $booking->status === 'confirmed';
        $deposit = 200000;
        $totalPaid = (float) ($booking->total_paid ?? 0);
        $remainingPayment = (float) ($booking->remaining_payment ?? ($booking->price - $totalPaid));

        $reasons = [];
        if ($booking->status === 'rented' || $booking->status === 'disewa') {
            $reasons[] = 'Unit iPhone untuk booking ini sudah diambil (status: Sedang Sewa).';
        } elseif ($booking->status === 'returned') {
            $reasons[] = 'Booking ini sudah selesai dikembalikan (status: Selesai).';
        } elseif ($booking->status === 'cancelled') {
            $reasons[] = 'Booking ini telah dibatalkan.';
        } elseif ($booking->status === 'pending') {
            $reasons[] = 'Booking masih menunggu konfirmasi admin.';
        }

        return response()->json([
            'status' => 'success',
            'can_pickup' => $canPickup,
            'data' => new BookingResource($booking),
            'verification' => [
                'can_pickup' => $canPickup,
                'status' => $booking->status,
                'status_label' => match ($booking->status) {
                    'confirmed' => 'Dikonfirmasi (Siap Diambil)',
                    'rented', 'disewa' => 'Sedang Sewa (Sudah Diambil)',
                    'returned' => 'Selesai Dikembalikan',
                    'cancelled' => 'Dibatalkan',
                    default => 'Menunggu Konfirmasi',
                },
                'reasons' => $reasons,
                'customer' => [
                    'name' => $booking->customer_name,
                    'phone' => $booking->customer_phone,
                    'email' => $booking->customer_email,
                    'address' => $booking->address,
                ],
                'collateral' => [
                    'type' => $booking->jaminan_type ?? 'KTP',
                    'is_secured' => false,
                    'notes' => 'Pastikan dokumen fisik ' . ($booking->jaminan_type ?? 'KTP') . ' asli ditahan dan disimpan di brankas outlet.',
                ],
                'financial' => [
                    'price' => (float) $booking->price,
                    'deposit' => (float) $deposit,
                    'total_bill' => (float) ($booking->price + $deposit),
                    'total_paid' => $totalPaid,
                    'remaining_payment' => $remainingPayment,
                    'payment_status' => $booking->payment_status ?? 'unpaid',
                    'is_paid' => $booking->payment_status === 'paid',
                ],
                'checklist' => [
                    [
                        'id' => 'id_card_matched',
                        'label' => 'Kesesuaian Identitas Penyewa (KTP/SIM/Paspor Asli)',
                        'description' => 'Nama dan foto pada kartu identitas fisik sesuai dengan data pemesan.',
                        'required' => true,
                    ],
                    [
                        'id' => 'phone_verified',
                        'label' => 'Verifikasi Nomor Kontak WhatsApp Aktif',
                        'description' => 'Nomor ' . $booking->customer_phone . ' aktif dan dapat dihubungi.',
                        'required' => true,
                    ],
                    [
                        'id' => 'collateral_secured',
                        'label' => 'Penerimaan Jaminan Fisik (' . ($booking->jaminan_type ?? 'KTP') . ')',
                        'description' => 'Dokumen fisik jaminan telah diserahkan oleh penyewa dan disimpan oleh kasir.',
                        'required' => true,
                    ],
                    [
                        'id' => 'terms_agreed',
                        'label' => 'Persetujuan Syarat & Ketentuan Sewa',
                        'description' => 'Penyewa telah membaca dan menandatangani/menyetujui ketentuan rental.',
                        'required' => true,
                    ],
                ],
            ],
        ]);
    }

    /**
     * Confirm unit pickup/handover to the customer and transition status to rented.
     * POST /api/v1/bookings/{idOrCode}/pickup
     * POST /api/v1/pickup/confirm/{idOrCode?}
     */
    public function confirmPickup(Request $request, ?string $idOrCode = null): JsonResponse
    {
        $targetIdOrCode = $idOrCode ?? $request->input('booking_code') ?? $request->input('booking_id');

        if (! $targetIdOrCode) {
            return response()->json([
                'status' => 'error',
                'message' => 'Parameter booking_code atau booking_id wajib disertakan.',
            ], 422);
        }

        $booking = Booking::with(['iphone', 'payment', 'user', 'affiliate'])
            ->where(function ($q) use ($targetIdOrCode) {
                if (is_numeric($targetIdOrCode)) {
                    $q->where('id', (int) $targetIdOrCode)
                        ->orWhere('booking_code', $targetIdOrCode);
                } else {
                    $q->where('booking_code', $targetIdOrCode);
                }
            })
            ->first();

        if (! $booking) {
            return response()->json([
                'status' => 'error',
                'message' => "Booking dengan kode atau ID '{$targetIdOrCode}' tidak ditemukan.",
            ], 404);
        }

        $user = $this->resolveUser($request);
        if (! $this->canAccessBooking($booking, $user)) {
            return response()->json([
                'status' => 'error',
                'message' => 'Anda tidak memiliki hak akses untuk memproses penyerahan booking ini.',
            ], 403);
        }

        // Validate current status
        if ($booking->status === 'rented' || $booking->status === 'disewa') {
            return response()->json([
                'status' => 'error',
                'message' => 'Unit iPhone untuk booking ini sudah diserahkan (status: Sedang Sewa).',
            ], 422);
        }

        if ($booking->status === 'returned') {
            return response()->json([
                'status' => 'error',
                'message' => 'Booking ini sudah selesai dikembalikan.',
            ], 422);
        }

        if ($booking->status === 'cancelled') {
            return response()->json([
                'status' => 'error',
                'message' => 'Booking ini telah dibatalkan.',
            ], 422);
        }

        if ($booking->status !== 'confirmed') {
            return response()->json([
                'status' => 'error',
                'message' => "Booking belum berstatus konfirmasi (status: {$booking->status}).",
            ], 422);
        }

        // Handle physical unit assignment if provided
        $unitIdentifier = $request->input('iphone_id') ?? $request->input('asset_code') ?? $request->input('unit_id');
        if ($unitIdentifier) {
            if ($request->filled('iphone_id') && is_numeric($request->input('iphone_id'))) {
                $assignedUnit = Iphones::find((int) $request->input('iphone_id'));
            } else {
                $assignedUnit = Iphones::where(function ($q) use ($unitIdentifier) {
                    if (is_numeric($unitIdentifier)) {
                        $q->where('id', (int) $unitIdentifier)
                            ->orWhere('asset_code', $unitIdentifier)
                            ->orWhere('serial_number', $unitIdentifier);
                    } else {
                        $q->where('asset_code', $unitIdentifier)
                            ->orWhere('serial_number', $unitIdentifier);
                    }
                })->first();
            }

            if (! $assignedUnit) {
                return response()->json([
                    'status' => 'error',
                    'message' => "Unit fisik iPhone '{$unitIdentifier}' tidak ditemukan.",
                ], 422);
            }

            // Check if unit is available or belongs to current booking
            $isAvailable = in_array(strtolower($assignedUnit->status ?? ''), ['ready', 'tersedia']);
            if (! $isAvailable && ($assignedUnit->id !== $booking->iphone_id || in_array($assignedUnit->status, ['maintenance', 'rented']))) {
                return response()->json([
                    'status' => 'error',
                    'message' => "Unit iPhone '{$assignedUnit->name}' ({$assignedUnit->asset_code}) sedang tidak tersedia (status: {$assignedUnit->status}).",
                ], 422);
            }

            // Release previous unit if changed
            if ($booking->iphone_id && $booking->iphone_id !== $assignedUnit->id) {
                $prevUnit = Iphones::find($booking->iphone_id);
                if ($prevUnit && in_array($prevUnit->status, ['booked', 'rented'])) {
                    $prevUnit->update(['status' => 'ready']);
                }
            }

            $booking->iphone_id = $assignedUnit->id;
            $assignedUnit->update(['status' => 'rented']);
        } else {
            // Use currently assigned iPhone if exists
            if ($booking->iphone) {
                $booking->iphone->update(['status' => 'rented']);
            }
        }

        // Jaminan / Collateral
        if ($request->filled('jaminan_type')) {
            $booking->jaminan_type = $request->input('jaminan_type');
        }

        // Staff / Operator
        $staffId = $request->user()?->id ?? $request->input('picked_up_by') ?? $request->input('staff_id') ?? $booking->user_id;
        $now = now();

        $booking->status = 'confirmed';
        $booking->save();

        // Fresh load with relation
        $booking->load(['iphone', 'payment', 'user', 'affiliate']);

        $deposit = 200000;
        $handoverSummary = [
            'booking_code' => $booking->booking_code,
            'customer_name' => $booking->customer_name,
            'customer_phone' => $booking->customer_phone,
            'unit_name' => $booking->iphone?->name ?? 'iPhone Unit',
            'serial_number' => $booking->iphone?->serial_number ?? '-',
            'asset_code' => $booking->iphone?->asset_code ?? '-',
            'collateral_type' => $booking->jaminan_type ?? 'KTP Asli',
            'collateral_received' => true,
            'picked_up_at' => $now->toIso8601String(),
            'picked_up_at_formatted' => $now->format('d M Y, H:i'),
            'duration' => (int) $booking->duration,
            'start_booking_date' => $booking->start_booking_date,
            'end_booking_date' => $booking->end_booking_date,
            'price' => (float) $booking->price,
            'deposit' => (float) $deposit,
            'picked_up_by' => $staffId,
        ];

        return response()->json([
            'status' => 'success',
            'message' => 'Penyerahan unit iPhone berhasil dikonfirmasi. Status booking kini disewa (rented).',
            'data' => new BookingResource($booking),
            'handover_summary' => $handoverSummary,
        ]);
    }

    /**
     * Store a newly created booking (walk-in customer or manual reservation).
     * POST /api/v1/bookings
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'customer_name' => ['required', 'string', 'max:255'],
            'customer_phone' => ['required', 'string', 'max:50'],
            'customer_email' => ['nullable', 'email', 'max:255'],
            'address' => ['nullable', 'string', 'max:500'],
            'iphone_id' => ['required', 'exists:iphones,id'],
            'start_booking_date' => ['required', 'date'],
            'end_booking_date' => ['required', 'date', 'after_or_equal:start_booking_date'],
            'start_time' => ['nullable', 'string'],
            'end_time' => ['nullable', 'string'],
            'duration' => ['nullable', 'integer', 'min:1'],
            'price' => ['required', 'numeric', 'min:0'],
            'deposit_amount' => ['nullable', 'numeric', 'min:0'],
            'deposit' => ['nullable', 'numeric', 'min:0'],
            'jaminan_type' => ['nullable', 'string', 'max:100'],
            'pickup_type' => ['nullable', 'string', 'max:100'],
            'payment_status' => ['nullable', 'string', 'in:unpaid,partial,paid'],
            'amount_paid' => ['nullable', 'numeric', 'min:0'],
            'payment_method' => ['nullable', 'string', 'max:50'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        $iphone = Iphones::findOrFail($validated['iphone_id']);

        $authUser = $this->resolveUser($request);

        // Security check: If authenticated user belongs to an affiliate, verify that iPhone belongs to their affiliate
        $isSuperAdmin = $authUser && method_exists($authUser, 'hasRole') && $authUser->hasRole('super-admin');
        $isGlobalAdmin = $authUser && method_exists($authUser, 'hasRole') && $authUser->hasRole('admin') && empty($authUser->affiliate_id);

        $isAffiliateUser = $authUser && (
            (method_exists($authUser, 'hasRole') && ($authUser->hasRole('affiliate-admin') || $authUser->hasRole('affiliate')))
            || (!empty($authUser->affiliate_id) && !$isSuperAdmin && !$isGlobalAdmin)
        );

        if ($isAffiliateUser) {
            $userAffiliateId = $authUser->affiliate_id;
            if (! $userAffiliateId || (int) $iphone->affiliate_id !== (int) $userAffiliateId) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Akses ditolak: Unit iPhone ini tidak terdaftar pada cabang/affiliate Anda.',
                ], 403);
            }
        }

        // Cek jika unit dalam status perawatan / tidak siap fisik
        if (in_array(strtolower($iphone->status ?? ''), ['maintenance', 'perawatan', 'lost', 'hilang', 'retired', 'nonaktif', 'in_transit', 'transferred', 'mutasi'])) {
            return response()->json([
                'status' => 'error',
                'message' => "Unit iPhone '{$iphone->name}' ({$iphone->asset_code}) sedang dalam status '{$iphone->status}' dan tidak dapat disewa.",
            ], 422);
        }

        $startDate = Carbon::parse($validated['start_booking_date'])->toDateString();
        $endDate = Carbon::parse($validated['end_booking_date'])->toDateString();
        $startTime = !empty($validated['start_time']) ? substr($validated['start_time'], 0, 5) : '09:00';
        $endTime = !empty($validated['end_time']) ? substr($validated['end_time'], 0, 5) : '18:00';

        $startDt = Carbon::parse("{$startDate} {$startTime}", 'Asia/Jakarta');
        $endDt = Carbon::parse("{$endDate} {$endTime}", 'Asia/Jakarta');

        $duration = (int) ($validated['duration'] ?? 24);
        if ($duration < 5) {
            $duration = $duration * 24;
        }

        if ($endDt->lte($startDt)) {
            $endDt = $startDt->copy()->addHours(max(1, $duration));
            $endDate = $endDt->toDateString();
            $endTime = $endDt->format('H:i');
        }

        // Cek ketersediaan unit untuk jadwal sewa yang diminta menggunakan model source-of-truth isAvailableForPeriod
        if (! $iphone->isAvailableForPeriod($startDt, $endDt, null, true)) {
            return response()->json([
                'status' => 'error',
                'message' => "Unit iPhone '{$iphone->name}' ({$iphone->serial_number}) tidak tersedia untuk jadwal yang dipilih ({$startDate} {$startTime} s/d {$endDate} {$endTime}). Mohon pilih unit lain atau sesuaikan jadwal sewa.",
            ], 422);
        }

        // Pastikan iPhone yang sedang disewa aktif saat ini tidak dapat disewa tumpang tindih
        $isRented = in_array(strtolower($iphone->status ?? ''), ['rented', 'disewa']);
        $activeRental = Booking::where('iphone_id', $iphone->id)
            ->whereIn('status', ['rented', 'disewa'])
            ->whereDoesntHave('returns')
            ->first();

        $now = Carbon::now('Asia/Jakarta');
        if (($isRented || $activeRental) && $startDt->lte($now)) {
            $renterInfo = $activeRental ? " oleh pelanggan '{$activeRental->customer_name}' (Kode Booking: {$activeRental->booking_code})" : '';
            return response()->json([
                'status' => 'error',
                'message' => "Unit iPhone '{$iphone->name}' ({$iphone->asset_code}) saat ini sedang disewa{$renterInfo} dan tidak dapat disewa lagi. Silakan pilih unit iPhone yang berstatus Tersedia.",
            ], 422);
        }

        // Generate unique booking code: SKY + ymd + 4 random uppercase chars
        do {
            $bookingCode = 'SKY' . Carbon::now()->format('ymd') . strtoupper(Str::random(4));
        } while (Booking::where('booking_code', $bookingCode)->exists());
        $deposit = (float) ($validated['deposit_amount'] ?? $validated['deposit'] ?? 200000);
        $price = (float) $validated['price'];

        $amountPaid = (float) ($validated['amount_paid'] ?? 0);
        $paymentStatus = $validated['payment_status'] ?? ($amountPaid >= $price ? 'paid' : ($amountPaid > 0 ? 'partial' : 'unpaid'));
        $remainingPayment = max(0, $price - $amountPaid);

        $rawPickup = strtolower($validated['pickup_type'] ?? 'pickup');
        $pickupType = (str_contains($rawPickup, 'delivery') || str_contains($rawPickup, 'antar')) ? 'delivery' : 'pickup';

        $rawJaminan = $validated['jaminan_type'] ?? 'KTP';
        $allowedJaminan = ['KTP', 'KK', 'Kartu Pelajar', 'SIM', 'Kartu Identitas Mahasiswa', 'Kartu Identitas Anak'];
        $cleanJaminan = 'KTP';
        foreach ($allowedJaminan as $aj) {
            if (stripos($rawJaminan, $aj) !== false || $rawJaminan === $aj) {
                $cleanJaminan = $aj;
                break;
            }
        }

        $authUser = $this->resolveUser($request);
        $userId = $authUser?->id ?? User::first()?->id ?? null;
        $affiliateId = $authUser?->affiliate_id ?? $iphone->affiliate_id ?? null;

        $booking = Booking::create([
            'booking_code' => $bookingCode,
            'customer_name' => $validated['customer_name'],
            'customer_phone' => $validated['customer_phone'],
            'customer_email' => $validated['customer_email'] ?? null,
            'address' => $validated['address'] ?? 'Datang Langsung (Walk-in Outlet)',
            'iphone_id' => $iphone->id,
            'start_booking_date' => $startDate,
            'end_booking_date' => $endDate,
            'start_time' => $validated['start_time'] ?? '09:00',
            'end_time' => $validated['end_time'] ?? '18:00',
            'requested_booking_date' => Carbon::now()->toDateString(),
            'requested_time' => Carbon::now()->toTimeString(),
            'duration' => $duration,
            'price' => $price,
            'status' => 'confirmed', // Siap untuk serah terima / pickup
            'pickup_type' => $pickupType,
            'jaminan_type' => $cleanJaminan,
            'payment_status' => $paymentStatus,
            'user_id' => $userId,
            'affiliate_id' => $affiliateId,
            'created' => Carbon::now(),
        ]);

        // If initial payment recorded, create payment record
        if ($amountPaid > 0) {
            $paymentMethod = $validated['payment_method'] ?? 'Tunai';
            $payment = Payment::firstOrCreate(
                ['slug' => Str::slug($paymentMethod) ?: 'tunai'],
                ['name' => $paymentMethod, 'is_active' => true]
            );

            BookingPayment::create([
                'payment_code' => 'PAY' . Carbon::now()->format('ymd') . strtoupper(Str::random(4)),
                'booking_id' => $booking->id,
                'payment_id' => $payment->id,
                'amount' => $amountPaid,
                'type' => $amountPaid >= $price ? 'payment' : 'dp',
                'paid_at' => Carbon::now(),
                'user_id' => $userId,
                'note' => 'Pembayaran saat buat booking walk-in (' . $paymentMethod . ')',
                'pay' => $amountPaid,
                'change' => 0,
            ]);

            $booking->update(['payment_id' => $payment->id]);
        }

        $booking->load(['iphone', 'payment', 'bookingPayments', 'user']);

        // Notifikasi ke Customer, Telegram Admin, dan Fonnte Group (RentIphoneWizard.php)
        $telegramToken = config('services.telegram.bot_token');
        $chatId = config('services.telegram.chat_id');
        $whatsappToken = config('services.fonnte.token');
        $groupId = config('services.fonnte.group_id');
        $sendWhatsapp = $request->boolean('send_whatsapp', false);

        $message = "Halo {$booking->customer_name},\n\n"
            . "Terima kasih telah melakukan booking di *SkyRental*.\n\n"
            . "Berikut adalah detail booking Anda:\n"
            . "--------------------------------------\n"
            . "Kode Booking : *{$booking->booking_code}*\n"
            . "Perangkat    : {$iphone->name} {$iphone->serial_number}\n"
            . "Tanggal      : {$booking->start_booking_date}\n"
            . "Waktu        : {$booking->start_time}\n"
            . "Durasi       : {$booking->duration} jam\n"
            . 'Total Biaya  : Rp' . number_format($booking->price, 0, ',', '.') . "\n"
            . "--------------------------------------\n\n"
            . "Mohon segera melakukan pembayaran *maksimal 30 menit* setelah pesan ini diterima.\n"
            . 'Apabila pembayaran belum kami terima hingga batas waktu tersebut, '
            . "maka booking akan *dibatalkan secara otomatis*.\n\n"
            . 'Setelah melakukan pembayaran, silakan lakukan konfirmasi dengan membalas pesan ini '
            . "atau mengirim bukti pembayaran melalui WhatsApp ini.\n\n"
            . "Untuk memeriksa status booking, silakan kunjungi:\n"
            . url('/booking-status') . "\n\n"
            . "Terima kasih atas kerja samanya.\n"
            . 'SkyRental';

        $groupMessage = "BOOKING BARU MASUK\n\n"
            . "Perangkat    : {$iphone->name} {$iphone->serial_number}\n"
            . "Nama         : {$booking->customer_name}\n"
            . "No. HP       : {$booking->customer_phone}\n"
            . "Alamat       : {$booking->address}\n"
            . "Jaminan      : {$booking->jaminan_type}\n"
            . "Email        : {$booking->customer_email}\n\n"
            . "Kode Booking : {$booking->booking_code}\n"
            . "Tanggal      : {$booking->start_booking_date}\n"
            . "Waktu        : {$booking->start_time}\n"
            . "Durasi       : {$booking->duration} jam\n"
            . 'Total Biaya  : Rp' . number_format($booking->price, 0, ',', '.') . "\n\n"
            . "Status       : {$booking->status} \n"
            . "Admin Panel:\n"
            . url('/admin/bookings/');

        $adminMessage = "<b>Booking Baru Diterima</b>\n\n"
            . "<b>Nama</b> : {$booking->customer_name}\n"
            . "<b>HP</b>   : {$booking->customer_phone}\n"
            . "<b>Email</b>: {$booking->customer_email}\n\n"
            . "<b>Kode Booking</b>: {$booking->booking_code}\n"
            . "<b>Perangkat</b>   : {$iphone->name}\n"
            . "<b>Tanggal</b>     : {$booking->start_booking_date}\n"
            . "<b>Waktu</b>       : {$booking->start_time}\n"
            . "<b>Durasi</b>      : {$booking->duration} jam\n"
            . '<b>Total Biaya</b>: Rp' . number_format($booking->price, 0, ',', '.') . "\n\n"
            . "🔗 <a href='" . url('/admin/bookings/' . $booking->id) . "'>Lihat detail di Admin Panel</a>";

        if ($sendWhatsapp && $whatsappToken) {
            try {
                Http::timeout(10)->withHeaders([
                    'Authorization' => $whatsappToken,
                ])->post('https://api.fonnte.com/send', [
                    'target' => $this->formatPhoneNumber($booking->customer_phone),
                    'message' => $message,
                ]);
            } catch (\Exception $e) {
                logger()->error('Fonnte Customer Error: ' . $e->getMessage());
            }
        }

        // Kirim ke Telegram
        if ($telegramToken && $chatId) {
            try {
                Http::timeout(10)->post("https://api.telegram.org/bot{$telegramToken}/sendMessage", [
                    'chat_id' => $chatId,
                    'text' => $adminMessage,
                    'parse_mode' => 'HTML',
                ]);
            } catch (\Exception $e) {
                logger()->error('Telegram Error: ' . $e->getMessage());
            }
        }

        // Kirim ke Group Fonnte
        if ($whatsappToken && $groupId) {
            try {
                Http::timeout(10)->withHeaders([
                    'Authorization' => $whatsappToken,
                ])->post('https://api.fonnte.com/send', [
                    'target' => $groupId,
                    'message' => $groupMessage,
                ]);
            } catch (\Exception $e) {
                logger()->error('Fonnte Group Error: ' . $e->getMessage());
            }
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Booking baru berhasil dibuat.',
            'data' => new BookingResource($booking),
        ], 201);
    }

    /**
     * Delete a booking from database.
     * DELETE /api/v1/bookings/{idOrCode}
     */
    public function destroy(Request $request, string $idOrCode): JsonResponse
    {
        $booking = Booking::where('id', $idOrCode)
            ->orWhere('booking_code', $idOrCode)
            ->first();

        if (!$booking) {
            return response()->json([
                'status' => 'error',
                'message' => 'Data booking tidak ditemukan.',
            ], 404);
        }

        $user = $this->resolveUser($request);
        if (! $this->canAccessBooking($booking, $user)) {
            return response()->json([
                'status' => 'error',
                'message' => 'Anda tidak memiliki hak akses untuk menghapus booking ini.',
            ], 403);
        }

        $bookingCode = $booking->booking_code;
        $customerName = $booking->customer_name;

        // Delete associated payments if needed
        BookingPayment::where('booking_id', $booking->id)->delete();
        $booking->delete();

        return response()->json([
            'status' => 'success',
            'message' => "Booking #{$bookingCode} atas nama {$customerName} berhasil dihapus.",
        ]);
    }

    /**
     * Format phone number helper to international format 62...
     */
    public function formatPhoneNumber($phone, $mode = '62'): string
    {
        $digits = preg_replace('/\D/', '', (string) $phone);

        if (substr($digits, 0, 2) === '62') {
            $normalized = $digits;
        } elseif (substr($digits, 0, 2) === '60' || substr($digits, 0, 2) === '65') {
            $normalized = $digits;
        } elseif (substr($digits, 0, 1) === '0') {
            $normalized = '62' . substr($digits, 1);
        } else {
            $normalized = '62' . $digits;
        }

        if ($mode === '0') {
            return '0' . substr($normalized, 2);
        }

        return $normalized;
    }

    /**
     * Validasi nomor WhatsApp menggunakan Fonnte API.
     * Logika sama dengan RentIphoneWizard.php nextStep 2.
     * POST /api/v1/validate-whatsapp
     */
    public function validateWhatsApp(Request $request): JsonResponse
    {
        $phone = $request->input('phone', $request->input('target'));
        if (empty($phone)) {
            return response()->json([
                'status' => 'error',
                'registered' => false,
                'message' => 'Nomor WhatsApp wajib diisi.',
            ], 422);
        }

        // Hapus semua karakter non-digit
        $digits = preg_replace('/\D/', '', (string) $phone);

        // Bersihkan jika ada duplikasi kode negara (misal 62628...)
        if (substr($digits, 0, 4) === '6262') {
            $digits = substr($digits, 2);
        }

        // Validasi nomor tidak boleh semua angka sama (contoh: 000000000000)
        $isAllSame = preg_match('/^(\d)\1+$/', $digits);
        if ($isAllSame || strlen($digits) < 8 || strlen($digits) > 16) {
            return response()->json([
                'status' => 'error',
                'registered' => false,
                'message' => 'Nomor WhatsApp tidak valid. Format harus diawali dengan 08/628 dan terdiri dari 10-13 digit.',
            ], 422);
        }

        // Validasi prefix nomor seluler Indonesia (08, 628, atau 8) atau negara pendukung (+60, +65)
        $isValidPrefix = false;
        if (substr($digits, 0, 2) === '62' && substr($digits, 2, 1) === '8') {
            $isValidPrefix = true;
        } elseif (substr($digits, 0, 1) === '0' && substr($digits, 1, 1) === '8') {
            $isValidPrefix = true;
        } elseif (substr($digits, 0, 1) === '8') {
            $isValidPrefix = true;
        } elseif (substr($digits, 0, 2) === '60' || substr($digits, 0, 2) === '65') {
            $isValidPrefix = true;
        }

        if (!$isValidPrefix) {
            return response()->json([
                'status' => 'error',
                'registered' => false,
                'message' => 'Nomor WhatsApp tidak valid. Format harus diawali dengan 08/628 dan terdiri dari 10-13 digit.',
            ], 422);
        }

        $normalized = $this->formatPhoneNumber($digits);
        $whatsappToken = config('services.fonnte.token');

        if (empty($whatsappToken)) {
            // Jika token belum diset di environment, loloskan nomor yang formatnya valid
            return response()->json([
                'status' => 'success',
                'registered' => true,
                'target' => $normalized,
                'message' => 'Format nomor WhatsApp valid.',
            ]);
        }

        try {
            $response = Http::timeout(10)->withHeaders([
                'Authorization' => $whatsappToken,
            ])->post('https://api.fonnte.com/validate', [
                'target' => $normalized,
            ]);

            $data = $response->json();

            if (!empty($data['not_registered']) && in_array($normalized, (array) $data['not_registered'])) {
                return response()->json([
                    'status' => 'warning',
                    'registered' => false,
                    'target' => $normalized,
                    'message' => 'Nomor WhatsApp yang Anda masukkan tidak terdaftar di WhatsApp.',
                ], 422);
            }

            return response()->json([
                'status' => 'success',
                'registered' => true,
                'target' => $normalized,
                'message' => 'Nomor WhatsApp terdaftar.',
            ]);
        } catch (\Exception $e) {
            logger()->warning('Fonnte WhatsApp validate error: ' . $e->getMessage());

            return response()->json([
                'status' => 'success',
                'registered' => true,
                'target' => $normalized,
                'message' => 'Tidak dapat terhubung ke server Fonnte, validasi dilanjutkan.',
            ]);
        }
    }

    /**
     * Check if a booking can be extended by specified hours.
     * GET /api/v1/bookings/{idOrCode}/can-extend?hours={hours}
     */
    public function canExtend(Request $request, string $idOrCode): JsonResponse
    {
        $booking = Booking::with(['iphone.durations'])
            ->where(function ($q) use ($idOrCode) {
                if (is_numeric($idOrCode)) {
                    $q->where('id', (int) $idOrCode)
                        ->orWhere('booking_code', $idOrCode);
                } else {
                    $q->where('booking_code', $idOrCode);
                }
            })
            ->first();

        if (! $booking) {
            return response()->json([
                'status' => 'error',
                'message' => "Booking dengan kode atau ID '{$idOrCode}' tidak ditemukan.",
            ], 404);
        }

        $user = $this->resolveUser($request);
        if (! $this->canAccessBooking($booking, $user)) {
            return response()->json([
                'status' => 'error',
                'available' => false,
                'message' => 'Anda tidak memiliki hak akses untuk memeriksa perpanjangan booking ini.',
            ], 403);
        }

        if (in_array($booking->status, ['returned', 'cancelled'])) {
            return response()->json([
                'status' => 'error',
                'available' => false,
                'message' => 'Booking yang sudah selesai atau dibatalkan tidak dapat diperpanjang.',
            ], 422);
        }

        $hours = (int) $request->query('hours', $request->input('hours', 0));
        if ($hours <= 0) {
            return response()->json([
                'status' => 'error',
                'available' => false,
                'message' => 'Parameter jam tambahan (hours) harus lebih besar dari 0.',
            ], 422);
        }

        $available = $booking->canExtend($hours);

        $end = Carbon::parse(
            "{$booking->end_booking_date} {$booking->end_time}",
            'Asia/Jakarta'
        );
        $newEnd = $end->copy()->addHours($hours);

        return response()->json([
            'status' => 'success',
            'available' => $available,
            'hours' => $hours,
            'current_end' => $end->format('d M Y H:i'),
            'new_end' => $newEnd->format('d M Y H:i'),
            'new_end_date' => $newEnd->toDateString(),
            'new_end_time' => $newEnd->format('H:i'),
            'message' => $available
                ? 'Unit iPhone tersedia untuk penambahan jam sewa.'
                : 'Unit tidak tersedia untuk perpanjangan waktu pada jadwal tersebut karena ada jadwal booking lain.',
        ]);
    }

    /**
     * Extend rental duration of a booking (Tambah Jam).
     * POST /api/v1/bookings/{idOrCode}/extend
     */
    public function extend(Request $request, string $idOrCode): JsonResponse
    {
        $booking = Booking::with(['iphone.durations', 'payment', 'paymentTransactions'])
            ->where(function ($q) use ($idOrCode) {
                if (is_numeric($idOrCode)) {
                    $q->where('id', (int) $idOrCode)
                        ->orWhere('booking_code', $idOrCode);
                } else {
                    $q->where('booking_code', $idOrCode);
                }
            })
            ->first();

        if (! $booking) {
            return response()->json([
                'status' => 'error',
                'message' => "Booking dengan kode atau ID '{$idOrCode}' tidak ditemukan.",
            ], 404);
        }

        $user = $this->resolveUser($request);
        if (! $this->canAccessBooking($booking, $user)) {
            return response()->json([
                'status' => 'error',
                'message' => 'Anda tidak memiliki hak akses untuk menambah jam booking ini.',
            ], 403);
        }

        if (in_array($booking->status, ['returned', 'cancelled'])) {
            return response()->json([
                'status' => 'error',
                'message' => 'Booking yang sudah selesai atau dibatalkan tidak dapat diperpanjang.',
            ], 422);
        }

        $hours = (int) $request->input('hours', 0);
        if ($hours <= 0) {
            return response()->json([
                'status' => 'error',
                'message' => 'Parameter jam tambahan (hours) harus lebih besar dari 0.',
            ], 422);
        }

        if (! $booking->canExtend($hours)) {
            return response()->json([
                'status' => 'error',
                'message' => 'Unit tidak dapat diperpanjang karena bertabrakan dengan jadwal booking pelanggan lain.',
            ], 422);
        }

        // Hitung waktu selesai lama dan baru
        $oldEnd = Carbon::parse("{$booking->end_booking_date} {$booking->end_time}", 'Asia/Jakarta');
        $newEnd = $oldEnd->copy()->addHours($hours);

        // Eksekusi perpanjangan jam
        $booking->extendHours($hours);
        $booking->update([
            'reminder_sent' => false,
        ]);

        $price = (float) $request->input('price', 0);
        if ($price > 0) {
            $booking->price += $price;
            $booking->saveQuietly();
        }

        // Catat transaksi pembayaran jika disediakan
        if ($request->filled('payment_method') || $request->filled('pay') || $request->boolean('paid')) {
            $paymentMethodInput = $request->input('payment_method', 'tunai');
            $payment = Payment::where('slug', strtolower($paymentMethodInput))
                ->orWhere('name', 'like', "%{$paymentMethodInput}%")
                ->first() ?? $booking->payment ?? Payment::first();

            $pay = (float) $request->input('pay', $price);
            $change = max(0, $pay - $price);

            BookingPayment::create([
                'booking_id' => $booking->id,
                'payment_id' => $payment?->id ?? 1,
                'amount' => $price,
                'pay' => $pay,
                'change' => $change,
                'type' => 'extend',
                'paid_at' => Carbon::now('Asia/Jakarta'),
                'user_id' => $user?->id ?? User::first()?->id,
                'note' => $request->input('note', "Penambahan durasi sewa {$hours} jam"),
            ]);

            $booking->updatePaymentStatus();
        }

        $newEndFormatted = $newEnd->format('d M Y H:i');
        $this->sendExtendNotification($booking, $hours, $newEndFormatted);

        return response()->json([
            'status' => 'success',
            'message' => "Durasi sewa berhasil diperpanjang {$hours} jam hingga {$newEndFormatted}.",
            'hours_added' => $hours,
            'new_duration' => $booking->duration,
            'new_end' => $newEndFormatted,
            'new_end_date' => $booking->end_booking_date,
            'new_end_time' => $booking->end_time,
            'data' => new BookingResource($booking->fresh([
                'iphone.durations',
                'iphone.gallery',
                'payment',
                'user',
                'latestReturn',
                'paymentTransactions',
            ])),
        ]);
    }

    /**
     * Send extend notification via Telegram & WhatsApp (mirroring DetailBooking)
     */
    protected function sendExtendNotification(Booking $booking, int $hours, string $newEnd): void
    {
        try {
            $whatsappToken = config('services.fonnte.token');
            if (! empty($whatsappToken) && ! empty($booking->customer_phone)) {
                $target = $this->formatPhoneNumber($booking->customer_phone);
                $msg = "Halo {$booking->customer_name},\n\n"
                    . "Penambahan durasi sewa Anda di *SkyRental* telah *berhasil dikonfirmasi*.\n\n"
                    . "Berikut detail terbaru booking Anda:\n"
                    . "--------------------------------------\n"
                    . "Kode Booking : *{$booking->booking_code}*\n"
                    . "Perangkat    : {$booking->iphone?->name} {$booking->iphone?->serial_number}\n"
                    . "Tambah Waktu : {$hours} jam\n"
                    . "Selesai      : {$newEnd}\n"
                    . "--------------------------------------\n\n"
                    . "Penambahan waktu telah kami konfirmasi.\n"
                    . "Silakan menggunakan perangkat sesuai durasi terbaru.\n\n"
                    . "Terima kasih atas kepercayaan Anda 🙏\n"
                    . "SkyRental";

                Http::timeout(5)->withHeaders([
                    'Authorization' => $whatsappToken,
                ])->post('https://api.fonnte.com/send', [
                    'target' => $target,
                    'message' => $msg,
                ]);
            }

            $telegramToken = config('services.telegram.bot_token');
            $chatId = config('services.telegram.chat_id');
            if (! empty($telegramToken) && ! empty($chatId)) {
                $adminMsg = "<b>Tambah Durasi Berhasil</b>\n\n"
                    . "<b>Nama</b> : {$booking->customer_name}\n"
                    . "<b>HP</b>   : {$booking->customer_phone}\n"
                    . "<b>Email</b>: {$booking->customer_email}\n\n"
                    . "<b>Kode Booking</b> : {$booking->booking_code}\n"
                    . "<b>Perangkat</b>    : {$booking->iphone?->name} {$booking->iphone?->serial_number}\n"
                    . "<b>Tambah Waktu</b> : {$hours} jam\n"
                    . "<b>Waktu Selesai</b>: {$newEnd}\n\n"
                    . "<b>Status</b>       : Berhasil\n\n"
                    . "🔗 <a href='" . url('/admin/bookings/' . $booking->id) . "'>Lihat detail di Admin Panel</a>";

                Http::timeout(5)->post("https://api.telegram.org/bot{$telegramToken}/sendMessage", [
                    'chat_id' => $chatId,
                    'text' => $adminMsg,
                    'parse_mode' => 'HTML',
                ]);
            }
        } catch (\Exception $e) {
            logger()->warning('Send extend notification error: ' . $e->getMessage());
        }
    }
}