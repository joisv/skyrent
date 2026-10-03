<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\BookingResource;
use App\Http\Resources\ReceiptResource;
use App\Http\Resources\ReturnInspectionResource;
use App\Models\Booking;
use App\Models\BookingPayment;
use App\Models\ReturnIphone;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ReturnController extends Controller
{
    protected function resolveUser(Request $request): ?\App\Models\User
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

        if (method_exists($user, 'hasRole') && $user->hasRole('affiliate-admin')) {
            if ($user->affiliate_id) {
                return $booking->affiliate_id == $user->affiliate_id || $booking->user_id == $user->id;
            }
            return $booking->user_id == $user->id;
        }

        // Staff / role lainnya: hanya boleh mengakses booking miliknya sendiri
        return $booking->user_id == $user->id;
    }

    /**
     * Display a paginated listing of active rentals (status = rented) for return processing
     * with multi-criteria search, overdue/due-today filters, and operational summary.
     *
     * GET /api/v1/returns/active-rentals
     * GET /api/v1/returns/active
     * GET /api/v1/rentals/active
     */
    public function activeRentals(Request $request): JsonResponse
    {
        $now = Carbon::now('Asia/Jakarta');
        $todayDate = $now->toDateString();

        $query = Booking::with([
            'iphone',
            'payment',
            'user',
            'latestReturn',
        ]);

        $user = $this->resolveUser($request);
        if ($user) {
            $isSuperAdmin = method_exists($user, 'hasRole') && $user->hasRole('super-admin');
            $isAdmin = method_exists($user, 'hasRole') && $user->hasRole('admin');
            $isAffiliateAdmin = method_exists($user, 'hasRole') && $user->hasRole('affiliate-admin');
            $isStaff = ! $isSuperAdmin && ! $isAdmin && ! $isAffiliateAdmin;

            if ($isStaff) {
                // Staff: hanya rental aktif booking miliknya sendiri
                $query->where('user_id', $user->id);
            } elseif ($isAffiliateAdmin) {
                if ($user->affiliate_id) {
                    $query->where(function ($q) use ($user) {
                        $q->where('affiliate_id', $user->affiliate_id)->orWhere('user_id', $user->id);
                    });
                } else {
                    $query->where('user_id', $user->id);
                }
            } elseif ($isAdmin && $user->affiliate_id) {
                $query->where(function ($q) use ($user) {
                    $q->where('affiliate_id', $user->affiliate_id)->orWhere('user_id', $user->id);
                });
            }
        }

        // Filter status: default to 'rented' for return workflow
        $status = $request->query('status', 'rented');
        if ($status !== 'all' && $status !== 'semua') {
            $query->where('status', $status);
        }

        // Multi-criteria keyword search (?q=, ?search=, ?query=)
        if ($search = $request->query('q') ?? $request->query('search') ?? $request->query('query')) {
            $search = trim($search);
            $query->where(function ($q) use ($search) {
                $q->where('booking_code', 'like', "%{$search}%")
                    ->orWhere('customer_name', 'like', "%{$search}%")
                    ->orWhere('customer_phone', 'like', "%{$search}%")
                    ->orWhere('customer_email', 'like', "%{$search}%")
                    ->orWhere('jaminan_type', 'like', "%{$search}%")
                    ->orWhereHas('iphone', function ($iq) use ($search) {
                        $iq->where('name', 'like', "%{$search}%")
                            ->orWhere('serial_number', 'like', "%{$search}%")
                            ->orWhere('asset_code', 'like', "%{$search}%");
                    });
            });
        }

        // Status filter by timing: Overdue, Due Today, Upcoming
        $isOverdueParam = filter_var($request->query('is_overdue', $request->query('isOverdueOnly')), FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
        $isDueTodayParam = filter_var($request->query('is_due_today', $request->query('isDueTodayOnly')), FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
        $isUpcomingParam = filter_var($request->query('is_upcoming', $request->query('isUpcomingOnly')), FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
        $timingFilter = strtolower($request->query('filter', ''));

        if ($isOverdueParam === true || in_array($timingFilter, ['overdue', 'terlambat'])) {
            $query->where(function ($q) use ($todayDate, $now) {
                $q->where('end_booking_date', '<', $todayDate)
                    ->orWhere(function ($sub) use ($todayDate, $now) {
                        $sub->where('end_booking_date', $todayDate)
                            ->whereNotNull('end_time')
                            ->where('end_time', '<', $now->format('H:i'));
                    });
            });
        } elseif ($isDueTodayParam === true || in_array($timingFilter, ['today', 'hari_ini', 'hari ini'])) {
            $query->where('end_booking_date', $todayDate);
        } elseif ($isUpcomingParam === true || in_array($timingFilter, ['upcoming', 'mendatang'])) {
            $query->where('end_booking_date', '>', $todayDate);
        }

        // Filter by iPhone model (?model= or ?modelFilter=)
        $modelFilter = $request->query('model', $request->query('modelFilter'));
        if ($modelFilter && ! in_array(strtolower($modelFilter), ['semua', 'all'])) {
            $query->whereHas('iphone', function ($iq) use ($modelFilter) {
                $iq->where('name', 'like', "%{$modelFilter}%");
            });
        }

        // Filter by Jaminan Type (?jaminan= or ?jaminanFilter=)
        $jaminanFilter = $request->query('jaminan', $request->query('jaminanFilter'));
        if ($jaminanFilter && ! in_array(strtolower($jaminanFilter), ['semua', 'all'])) {
            $query->where('jaminan_type', 'like', "%{$jaminanFilter}%");
        }

        // Compute Operational Return Summary for active rentals
        $summary = $this->calculateReturnSummary();

        // Sorting (?sort= or ?sortBy=)
        $sortBy = $request->query('sortBy', $request->query('sort', 'urgent'));
        switch ($sortBy) {
            case 'newest':
                $query->orderBy('start_booking_date', 'desc')->latest('id');
                break;
            case 'customerAsc':
                $query->orderBy('customer_name', 'asc');
                break;
            case 'depositDesc':
                $query->orderBy('price', 'desc');
                break;
            case 'urgent':
            default:
                // Overdue first (end_booking_date < today), then due today, then closest return date
                $query->orderByRaw("CASE 
                    WHEN end_booking_date < '{$todayDate}' THEN 1 
                    WHEN end_booking_date = '{$todayDate}' THEN 2 
                    ELSE 3 
                END ASC")
                ->orderBy('end_booking_date', 'asc')
                ->orderBy('end_time', 'asc');
                break;
        }

        // Pagination
        $perPage = min(100, max(1, (int) $request->query('per_page', 15)));
        $paginated = $query->paginate($perPage);

        return response()->json([
            'status' => 'success',
            'data' => BookingResource::collection($paginated->items()),
            'summary' => $summary,
            'meta' => [
                'current_page' => $paginated->currentPage(),
                'per_page' => $paginated->perPage(),
                'total' => $paginated->total(),
                'last_page' => $paginated->lastPage(),
                'from' => $paginated->firstItem(),
                'to' => $paginated->lastItem(),
            ],
        ]);
    }

    /**
     * Get operational summary statistics for the return dashboard.
     * GET /api/v1/returns/summary
     */
    public function summary(): JsonResponse
    {
        return response()->json([
            'status' => 'success',
            'data' => $this->calculateReturnSummary(),
        ]);
    }

    /**
     * Helper to calculate operational return metrics across active rentals.
     */
    private function calculateReturnSummary(): array
    {
        $now = Carbon::now('Asia/Jakarta');
        $todayDate = $now->toDateString();

        $activeQuery = Booking::where('status', 'rented');

        $totalActive = (clone $activeQuery)->count();

        $totalOverdue = (clone $activeQuery)->where(function ($q) use ($todayDate, $now) {
            $q->where('end_booking_date', '<', $todayDate)
                ->orWhere(function ($sub) use ($todayDate, $now) {
                    $sub->where('end_booking_date', $todayDate)
                        ->whereNotNull('end_time')
                        ->where('end_time', '<', $now->format('H:i'));
                });
        })->count();

        $totalDueToday = (clone $activeQuery)->where('end_booking_date', $todayDate)->count();

        $totalDeposit = 0.0;

        return [
            'total_active_rentals' => $totalActive,
            'total_overdue' => $totalOverdue,
            'total_due_today' => $totalDueToday,
            'total_active_deposit' => $totalDeposit,
            // Flutter camelCase compatibility
            'totalActiveRentals' => $totalActive,
            'totalOverdue' => $totalOverdue,
            'totalDueToday' => $totalDueToday,
            'totalActiveDeposit' => $totalDeposit,
        ];
    }

    /**
     * Store unit inspection results during iPhone return.
     * POST /api/v1/returns/inspect/{bookingIdOrCode?}
     * POST /api/v1/returns/inspection
     * POST /api/v1/bookings/{idOrCode}/inspect
     */
    public function inspect(Request $request, ?string $bookingIdOrCode = null): JsonResponse
    {
        $target = $bookingIdOrCode ?? $request->input('booking_code') ?? $request->input('booking_id');

        if (! $target) {
            return response()->json([
                'status' => 'error',
                'message' => 'Kode booking atau ID diperlukan.',
            ], 422);
        }

        $booking = Booking::with(['iphone', 'user', 'latestReturn'])
            ->where(function ($q) use ($target) {
                if (is_numeric($target)) {
                    $q->where('id', (int) $target)->orWhere('booking_code', $target);
                } else {
                    $q->where('booking_code', $target);
                }
            })
            ->first();

        if (! $booking) {
            return response()->json([
                'status' => 'error',
                'message' => "Booking dengan kode atau ID '{$target}' tidak ditemukan.",
            ], 404);
        }

        $user = $this->resolveUser($request);
        if ($user && ! $this->canAccessBooking($booking, $user)) {
            return response()->json([
                'status' => 'error',
                'message' => 'Anda tidak memiliki hak akses untuk memeriksa pengembalian booking ini.',
            ], 403);
        }

        if ($booking->status === 'cancelled') {
            return response()->json([
                'status' => 'error',
                'message' => "Booking dengan kode '{$booking->booking_code}' berstatus dibatalkan.",
            ], 422);
        }

        $validated = $request->validate([
            'physical_condition' => ['nullable', 'string', 'max:255'],
            'condition' => ['nullable', 'string', 'max:255'],
            'battery_health' => ['nullable', 'integer', 'min:0', 'max:100'],
            'is_icloud_signed_out' => ['nullable', 'boolean'],
            'is_passcode_removed' => ['nullable', 'boolean'],
            'is_find_my_off' => ['nullable', 'boolean'],
            'is_screen_responsive' => ['nullable', 'boolean'],
            'is_camera_normal' => ['nullable', 'boolean'],
            'is_buttons_normal' => ['nullable', 'boolean'],
            'accessories_returned' => ['nullable', 'array'],
            'late_fee' => ['nullable', 'numeric', 'min:0'],
            'damage_fee' => ['nullable', 'numeric', 'min:0'],
            'deposit_refunded' => ['nullable', 'numeric', 'min:0'],
            'refund_method' => ['nullable', 'string', 'max:100'],
            'staff_notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $now = Carbon::now('Asia/Jakarta');

        // Determine late fee
        $lateFee = isset($validated['late_fee']) ? (float) $validated['late_fee'] : null;
        if ($lateFee === null) {
            $lateFee = $this->calculateLateFee($booking, $now);
        }

        // Determine damage fee
        $damageFee = isset($validated['damage_fee']) ? (float) $validated['damage_fee'] : 0.0;

        // Accessories returned
        $accessories = $validated['accessories_returned'] ?? ['kabel', 'adaptor', 'box', 'case', 'tempered_glass'];

        $totalDeduction = $lateFee + $damageFee;

        // Deposit amount & refund
        $depositAmount = 0.0;
        $depositRefunded = 0.0;
        $customerShortage = 0.0;

        $physicalCondition = $validated['physical_condition'] ?? $validated['condition'] ?? 'Mulus (Sempurna)';

        $returnIphone = DB::transaction(function () use (
            $booking,
            $physicalCondition,
            $totalDeduction,
            $now
        ) {
            $return = ReturnIphone::firstOrNew(['booking_id' => $booking->id]);
            $return->fill([
                'returned_at' => $now,
                'condition' => $physicalCondition,
                'penalty_fee' => $totalDeduction,
            ]);
            $return->save();

            return $return;
        });

        $returnIphone->load(['booking.iphone', 'admin']);

        return response()->json([
            'status' => 'success',
            'message' => 'Hasil pemeriksaan unit berhasil disimpan.',
            'data' => new ReturnInspectionResource($returnIphone),
        ], 200);
    }

    /**
     * Preview calculation for unit inspection before confirming return.
     * GET /api/v1/returns/inspect/{bookingIdOrCode}
     * GET /api/v1/returns/{bookingIdOrCode}/inspection-preview
     */
    public function inspectionPreview(Request $request, string $bookingIdOrCode): JsonResponse
    {
        $booking = Booking::with(['iphone', 'latestReturn'])
            ->where(function ($q) use ($bookingIdOrCode) {
                if (is_numeric($bookingIdOrCode)) {
                    $q->where('id', (int) $bookingIdOrCode)->orWhere('booking_code', $bookingIdOrCode);
                } else {
                    $q->where('booking_code', $bookingIdOrCode);
                }
            })
            ->first();

        if (! $booking) {
            return response()->json([
                'status' => 'error',
                'message' => "Booking dengan kode atau ID '{$bookingIdOrCode}' tidak ditemukan.",
            ], 404);
        }

        $user = $this->resolveUser($request);
        if ($user && ! $this->canAccessBooking($booking, $user)) {
            return response()->json([
                'status' => 'error',
                'message' => 'Anda tidak memiliki hak akses untuk memeriksa pratinjau pengembalian booking ini.',
            ], 403);
        }

        $now = Carbon::now('Asia/Jakarta');
        $depositAmount = 0.0;
        $depositRefunded = 0.0;

        $endTimeStr = $booking->end_time ?: '23:59:59';
        $endDateTime = $booking->end_booking_date
            ? Carbon::parse("{$booking->end_booking_date} {$endTimeStr}", 'Asia/Jakarta')
            : null;

        $diffInMinutes = $endDateTime ? $endDateTime->diffInMinutes($now, false) : 0;
        $diffInHours = max(0.0, $diffInMinutes / 60);
        $isOverdue = $endDateTime && $diffInMinutes > 0;
        $isLate = $diffInMinutes > 90; // Web standard in DetailBooking.php: > 90 minutes is late

        $lateHours = max(0, (int) floor($diffInHours));
        $lateMinutes = max(0, (int) round(($diffInHours - $lateHours) * 60));
        $lateFee = $this->calculateLateFee($booking, $now);
        $daysLate = (int) floor($lateHours / 24);

        $durationText = ($lateHours == 0 && $lateMinutes == 0)
            ? '0 menit'
            : ($lateHours > 0 ? "{$lateHours} jam {$lateMinutes} menit" : "{$lateMinutes} menit");

        return response()->json([
            'status' => 'success',
            'data' => [
                'booking_code' => $booking->booking_code,
                'customer_name' => $booking->customer_name,
                'customer_phone' => $booking->customer_phone,
                'unit_name' => $booking->iphone?->name ?? 'iPhone Unit',
                'asset_code' => $booking->iphone?->asset_code,
                'serial_number' => $booking->iphone?->serial_number,
                'current_battery_health' => (int) ($booking->iphone?->battery_health ?? 100),
                'initial_deposit' => $depositAmount,
                'is_overdue' => $isOverdue,
                'is_late' => $isLate,
                'diff_hours' => round($diffInHours, 2),
                'hours_late' => $lateHours,
                'late_hours' => $lateHours,
                'late_minutes' => $lateMinutes,
                'duration_text' => $durationText,
                'days_late' => $daysLate,
                'estimated_late_fee' => $lateFee,
                'late_fee' => $lateFee,
                'estimated_deposit_refund' => $depositRefunded,
                'standard_accessories' => [
                    ['key' => 'kabel', 'name' => 'Kabel Lightning / Type-C Original', 'penalty_if_missing' => 50000],
                    ['key' => 'adaptor', 'name' => 'Adaptor Charger 20W', 'penalty_if_missing' => 100000],
                    ['key' => 'box', 'name' => 'Box Unit / Hard Pouch', 'penalty_if_missing' => 25000],
                    ['key' => 'case', 'name' => 'Clear Case Pelindung', 'penalty_if_missing' => 25000],
                    ['key' => 'tempered_glass', 'name' => 'Tempered Glass Terpasang', 'penalty_if_missing' => 25000],
                ],
                'condition_options' => [
                    ['label' => 'Mulus (Sempurna)', 'penalty' => 0],
                    ['label' => 'Lecet Ringan (Pemakaian Wajar)', 'penalty' => 0],
                    ['label' => 'Dent / Baret Dalam', 'penalty' => 100000],
                    ['label' => 'Layar Retak / Pecah', 'penalty' => 500000],
                    ['label' => 'Mati Total / Rusak Mesin', 'penalty' => 1500000],
                ],
            ],
        ]);
    }

    private function calculateLateFee(Booking $booking, Carbon $now): float
    {
        if (! $booking->end_booking_date) {
            return 0.0;
        }

        $endTimeStr = $booking->end_time ?: '23:59:59';
        $endDateTime = Carbon::parse("{$booking->end_booking_date} {$endTimeStr}", 'Asia/Jakarta');

        $diffInMinutes = $endDateTime->diffInMinutes($now, false);

        if ($diffInMinutes <= 0) {
            return 0.0;
        }

        $diffInHours = $diffInMinutes / 60;
        $lateHours = max(0, (int) floor($diffInHours));

        // Toleransi 1 jam sesuai DetailBooking.php
        $toleranceHours = 1;

        if ($lateHours <= $toleranceHours) {
            return 0.0;
        }

        $lateHours -= $toleranceHours;

        $durations = $booking->iphone
            ? $booking->iphone->durations()->orderByDesc('hours')->get()
            : collect([]);

        $remainingHours = $lateHours;
        $penalty = 0.0;

        foreach ($durations as $package) {
            if ($remainingHours < $package->hours) {
                continue;
            }

            $count = intdiv($remainingHours, $package->hours);
            $penalty += $count * $package->pivot->price;
            $remainingHours -= $count * $package->hours;

            if ($remainingHours <= 0) {
                break;
            }
        }

        // Sisa jam di bawah paket terkecil
        if ($remainingHours > 0) {
            $penalty += $remainingHours * 5000;
        }

        return (float) $penalty;
    }

    /**
     * Complete return process, transition booking to 'returned', restore unit status to 'ready',
     * process deposit refund, and produce thermal receipt.
     *
     * POST /api/v1/returns/complete/{bookingIdOrCode?}
     * POST /api/v1/returns/complete
     * POST /api/v1/bookings/{idOrCode}/return
     */
    public function completeReturn(Request $request, ?string $bookingIdOrCode = null): JsonResponse
    {
        $target = $bookingIdOrCode ?? $request->input('booking_code') ?? $request->input('booking_id');

        if (! $target) {
            return response()->json([
                'status' => 'error',
                'message' => 'Kode booking atau ID diperlukan.',
            ], 422);
        }

        $booking = Booking::with(['iphone', 'user', 'latestReturn', 'payment'])
            ->where(function ($q) use ($target) {
                if (is_numeric($target)) {
                    $q->where('id', (int) $target)->orWhere('booking_code', $target);
                } else {
                    $q->where('booking_code', $target);
                }
            })
            ->first();

        if (! $booking) {
            return response()->json([
                'status' => 'error',
                'message' => "Booking dengan kode atau ID '{$target}' tidak ditemukan.",
            ], 404);
        }

        $user = $this->resolveUser($request);
        if ($user && ! $this->canAccessBooking($booking, $user)) {
            return response()->json([
                'status' => 'error',
                'message' => 'Anda tidak memiliki hak akses untuk menyelesaikan pengembalian booking ini.',
            ], 403);
        }

        if ($booking->status === 'returned') {
            return response()->json([
                'status' => 'error',
                'error_code' => 'ALREADY_RETURNED',
                'message' => "Booking dengan kode '{$booking->booking_code}' sudah diselesaikan pengembaliannya.",
            ], 422);
        }

        if ($booking->status === 'cancelled') {
            return response()->json([
                'status' => 'error',
                'error_code' => 'BOOKING_CANCELLED',
                'message' => "Booking dengan kode '{$booking->booking_code}' berstatus dibatalkan.",
            ], 422);
        }

        if (! in_array($booking->status, ['rented', 'confirmed'])) {
            return response()->json([
                'status' => 'error',
                'error_code' => 'INVALID_STATUS',
                'message' => "Booking dengan kode '{$booking->booking_code}' belum berstatus disewa (status saat ini: {$booking->status}).",
            ], 422);
        }

        $validated = $request->validate([
            'physical_condition' => ['nullable', 'string', 'max:255'],
            'condition' => ['nullable', 'string', 'max:255'],
            'battery_health' => ['nullable', 'integer', 'min:0', 'max:100'],
            'battery_health_final' => ['nullable', 'integer', 'min:0', 'max:100'],
            'is_icloud_signed_out' => ['nullable', 'boolean'],
            'is_passcode_removed' => ['nullable', 'boolean'],
            'is_find_my_off' => ['nullable', 'boolean'],
            'is_screen_responsive' => ['nullable', 'boolean'],
            'is_camera_normal' => ['nullable', 'boolean'],
            'is_buttons_normal' => ['nullable', 'boolean'],
            'accessories_returned' => ['nullable', 'array'],
            'late_fee' => ['nullable', 'numeric', 'min:0'],
            'damage_fee' => ['nullable', 'numeric', 'min:0'],
            'deposit_refunded' => ['nullable', 'numeric', 'min:0'],
            'refund_method' => ['nullable', 'string', 'max:100'],
            'staff_notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $now = Carbon::now('Asia/Jakarta');

        $batteryHealth = $validated['battery_health_final']
            ?? $validated['battery_health']
            ?? $booking->iphone?->battery_health
            ?? 100;

        $physicalCondition = $validated['physical_condition']
            ?? $validated['condition']
            ?? 'Mulus (Sempurna)';

        $lateFee = isset($validated['late_fee'])
            ? (float) $validated['late_fee']
            : $this->calculateLateFee($booking, $now);

        $damageFee = isset($validated['damage_fee'])
            ? (float) $validated['damage_fee']
            : 0.0;

        $totalDeduction = $lateFee + $damageFee;

        $depositAmount = 0.0;
        $depositRefunded = 0.0;
        $customerShortage = 0.0;

        $refundMethod = $validated['refund_method'] ?? 'Tunai (Kasir)';
        $accessories = $validated['accessories_returned'] ?? ['kabel', 'adaptor', 'box', 'case', 'tempered_glass'];

        $result = DB::transaction(function () use (
            $booking,
            $now,
            $physicalCondition,
            $totalDeduction
        ) {
            // 1. Update Booking status to 'returned'
            $booking->update([
                'status' => 'returned',
            ]);

            // 2. Restore physical iPhone status to 'ready' (available)
            if ($booking->iphone) {
                $booking->iphone->update([
                    'status' => 'ready',
                ]);
            }

            // 3. Create or complete ReturnIphone record
            $return = ReturnIphone::firstOrNew(['booking_id' => $booking->id]);
            $return->fill([
                'returned_at' => $now,
                'condition' => $physicalCondition,
                'penalty_fee' => $totalDeduction,
            ]);
            $return->save();

            // 4. Create BookingPayment of type 'penalty' if there is a penalty fee
            if ($totalDeduction > 0) {
                BookingPayment::create([
                    'booking_id' => $booking->id,
                    'payment_id' => $booking->payment_id ?? 1,
                    'amount' => $totalDeduction,
                    'pay' => $totalDeduction,
                    'change' => 0,
                    'type' => 'penalty',
                    'paid_at' => $now,
                    'user_id' => auth()->id() ?? $booking->user_id,
                    'note' => 'Denda keterlambatan/kondisi fisik pengembalian unit',
                ]);
            }

            return $return;
        });

        $refreshedBooking = $booking->fresh([
            'iphone',
            'payment',
            'user',
            'paymentTransactions.payment',
            'latestReturn',
        ]);

        // Generate return receipt
        $receiptController = new ReceiptController();
        $receiptData = $receiptController->formatReceiptData($refreshedBooking, 'returnUnit', $request);

        return response()->json([
            'status' => 'success',
            'message' => 'Pengembalian iPhone berhasil diselesaikan dan unit telah kembali ke status tersedia.',
            'data' => new BookingResource($refreshedBooking),
            'receipt' => new ReceiptResource($receiptData),
            'return_details' => new ReturnInspectionResource($result),
            'summary' => [
                'booking_code' => $booking->booking_code,
                'booking_status' => 'returned',
                'unit_status' => 'ready',
                'battery_health' => $batteryHealth,
                'late_fee' => $lateFee,
                'damage_fee' => $damageFee,
                'total_deduction' => $totalDeduction,
                'deposit_refunded' => $depositRefunded,
                'customer_shortage' => $customerShortage,
                'refund_method' => $refundMethod,
            ],
        ], 200);
    }
}
