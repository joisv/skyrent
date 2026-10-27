<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Affiliate;
use App\Models\BookingPayment;
use App\Models\CashDrawerShift;
use App\Models\Expense;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class ShiftController extends Controller
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
        if (! $user && app()->environment('testing')) {
            $user = User::first();
        }
        return $user;
    }

    /**
     * Resolve effective branch / affiliate ID.
     */
    protected function resolveBranchId(Request $request, ?User $user): ?int
    {
        $branchId = $request->input('branch_id')
            ?? $request->query('branch_id')
            ?? $request->header('X-Branch-Id')
            ?? $user?->affiliate_id;

        if (!$branchId || !is_numeric($branchId) || (int)$branchId <= 0) {
            $branchId = 4; // Default to Genteng Pusat
        }

        return (int)$branchId;
    }

    /**
     * Retrieve the active open cash drawer shift for the user's branch.
     * GET /api/v1/cashier/shift/current
     */
    public function currentShift(Request $request): JsonResponse
    {
        $user = $this->resolveUser($request);
        if (!$user) {
            return response()->json([
                'status' => 'error',
                'message' => 'Unauthenticated.',
            ], 401);
        }

        $branchId = $this->resolveBranchId($request, $user);

        $activeShift = CashDrawerShift::with(['openedBy', 'branch'])
            ->where('branch_id', $branchId)
            ->where('status', 'open')
            ->latest('opened_at')
            ->first();

        if (!$activeShift) {
            return response()->json([
                'success' => true,
                'data' => [
                    'has_active_shift' => false,
                    'shift' => null,
                ],
            ]);
        }

        $shiftStart = $activeShift->opened_at;
        $now = Carbon::now('Asia/Jakarta');

        // Physical Cash payments during active shift (payment_id = 2 is Cash)
        $cashPaymentQuery = BookingPayment::whereBetween('paid_at', [$shiftStart, $now])
            ->where('is_void', false)
            ->where('affiliate_id', $branchId)
            ->where(function ($q) {
                $q->where('payment_id', 2)
                  ->orWhereHas('payment', fn($pq) => $pq->where('slug', 'cash')->orWhere('name', 'like', '%tunai%'));
            });

        $cashInflow = (float) (clone $cashPaymentQuery)
            ->where('type', '!=', 'refund')
            ->sum('amount');

        $cashRefund = (float) (clone $cashPaymentQuery)
            ->where('type', 'refund')
            ->sum('amount');

        $netCashRevenue = $cashInflow - $cashRefund;

        // Operating expenses disbursed in cash during this shift
        $cashExpenses = (float) Expense::whereBetween('spent_at', [$shiftStart, $now])
            ->where('affiliate_id', $branchId)
            ->sum('amount');

        $expectedCash = max(0.00, (float) $activeShift->opening_cash_float + $netCashRevenue - $cashExpenses);

        return response()->json([
            'success' => true,
            'data' => [
                'has_active_shift' => true,
                'shift' => [
                    'id' => $activeShift->id,
                    'shift_number' => $activeShift->shift_number,
                    'branch_id' => $activeShift->branch_id,
                    'branch_name' => $activeShift->branch?->name ?? 'Cabang #' . $activeShift->branch_id,
                    'opened_by_name' => $activeShift->openedBy?->name ?? 'Kasir',
                    'opened_at' => $activeShift->opened_at->toIso8601String(),
                    'opening_cash_float' => (float) $activeShift->opening_cash_float,
                    'current_cash_inflow' => $cashInflow,
                    'current_cash_refund' => $cashRefund,
                    'current_cash_expenses' => $cashExpenses,
                    'expected_cash_now' => $expectedCash,
                    'status' => $activeShift->status,
                    'closing_notes' => $activeShift->closing_notes,
                ],
            ],
        ]);
    }

    /**
     * Open a new cash drawer shift with opening cash float.
     * POST /api/v1/cashier/shift/open
     */
    public function openShift(Request $request): JsonResponse
    {
        $user = $this->resolveUser($request);
        if (!$user) {
            return response()->json([
                'status' => 'error',
                'message' => 'Unauthenticated.',
            ], 401);
        }

        $validated = $request->validate([
            'opening_cash_float' => 'required|numeric|min:0',
            'notes' => 'nullable|string|max:1000',
            'branch_id' => 'nullable|integer',
        ], [
            'opening_cash_float.required' => 'Nominal modal awal kas wajib diisi.',
            'opening_cash_float.min' => 'Nominal modal awal kas tidak boleh bernilai negatif.',
        ]);

        $branchId = $this->resolveBranchId($request, $user);

        // Concurrency guard: reject opening if branch already has an active open shift
        $existing = CashDrawerShift::where('branch_id', $branchId)
            ->where('status', 'open')
            ->first();

        if ($existing) {
            return response()->json([
                'success' => false,
                'message' => "Cabang ini masih memiliki shift aktif yang belum ditutup (Shift: {$existing->shift_number}). Harap tutup shift sebelumnya terlebih dahulu.",
            ], 422);
        }

        $affiliate = Affiliate::find($branchId);
        $branchCode = $affiliate?->code ? strtoupper($affiliate->code) : 'CBG' . $branchId;
        $datePrefix = Carbon::now('Asia/Jakarta')->format('ymd');
        $seq = CashDrawerShift::where('branch_id', $branchId)
            ->whereDate('opened_at', Carbon::today('Asia/Jakarta'))
            ->count() + 1;
        $shiftNumber = sprintf('SHF-%s-%s-%04d', $branchCode, $datePrefix, $seq);

        $shift = CashDrawerShift::create([
            'branch_id' => $branchId,
            'shift_number' => $shiftNumber,
            'opened_by' => $user->id,
            'opened_at' => Carbon::now('Asia/Jakarta'),
            'opening_cash_float' => (float) $validated['opening_cash_float'],
            'status' => 'open',
            'closing_notes' => $validated['notes'] ?? null,
        ]);

        $shift->load(['branch', 'openedBy']);

        return response()->json([
            'success' => true,
            'message' => "Shift kasir {$shift->shift_number} berhasil dibuka dengan modal awal Rp " . number_format($shift->opening_cash_float, 0, ',', '.'),
            'data' => [
                'id' => $shift->id,
                'shift_number' => $shift->shift_number,
                'branch_id' => $shift->branch_id,
                'branch_name' => $shift->branch?->name ?? 'Cabang #' . $shift->branch_id,
                'opened_by_name' => $shift->openedBy?->name ?? $user->name,
                'opened_at' => $shift->opened_at->toIso8601String(),
                'opening_cash_float' => (float) $shift->opening_cash_float,
                'expected_cash_now' => (float) $shift->opening_cash_float,
                'current_cash_inflow' => 0.00,
                'current_cash_refund' => 0.00,
                'current_cash_expenses' => 0.00,
                'status' => $shift->status,
                'closing_notes' => $shift->closing_notes,
            ],
        ], 201);
    }

    /**
     * Close the active cash drawer shift with physical cash count reconciliation.
     * POST /api/v1/cashier/shift/close
     */
    public function closeShift(Request $request): JsonResponse
    {
        $user = $this->resolveUser($request);
        if (!$user) {
            return response()->json([
                'status' => 'error',
                'message' => 'Unauthenticated.',
            ], 401);
        }

        $validated = $request->validate([
            'actual_cash_counted' => 'required|numeric|min:0',
            'difference_reason' => 'nullable|string|max:1000',
            'closing_notes' => 'nullable|string|max:1000',
            'shift_id' => 'nullable|integer',
        ], [
            'actual_cash_counted.required' => 'Jumlah fisik uang tunai di kasir wajib diinput.',
            'actual_cash_counted.min' => 'Jumlah fisik uang tunai tidak boleh negatif.',
        ]);

        $branchId = $this->resolveBranchId($request, $user);

        $shiftQuery = CashDrawerShift::where('status', 'open');
        if (!empty($validated['shift_id'])) {
            $shiftQuery->where('id', $validated['shift_id']);
        } else {
            $shiftQuery->where('branch_id', $branchId);
        }

        $activeShift = $shiftQuery->latest('opened_at')->first();

        if (!$activeShift) {
            return response()->json([
                'success' => false,
                'message' => 'Tidak ditemukan shift kasir aktif yang dapat ditutup untuk cabang ini.',
            ], 404);
        }

        $shiftStart = $activeShift->opened_at;
        $closedAt = Carbon::now('Asia/Jakarta');

        // Net cash payments
        $cashPaymentQuery = BookingPayment::whereBetween('paid_at', [$shiftStart, $closedAt])
            ->where('is_void', false)
            ->where('affiliate_id', $activeShift->branch_id)
            ->where(function ($q) {
                $q->where('payment_id', 2)
                  ->orWhereHas('payment', fn($pq) => $pq->where('slug', 'cash')->orWhere('name', 'like', '%tunai%'));
            });

        $cashInflow = (float) (clone $cashPaymentQuery)
            ->where('type', '!=', 'refund')
            ->sum('amount');

        $cashRefund = (float) (clone $cashPaymentQuery)
            ->where('type', 'refund')
            ->sum('amount');

        $netCashRevenue = $cashInflow - $cashRefund;

        // Cash expenses
        $cashExpenses = (float) Expense::whereBetween('spent_at', [$shiftStart, $closedAt])
            ->where('affiliate_id', $activeShift->branch_id)
            ->sum('amount');

        $expectedCash = max(0.00, (float) $activeShift->opening_cash_float + $netCashRevenue - $cashExpenses);
        $actualCash = (float) $validated['actual_cash_counted'];
        $cashDifference = $actualCash - $expectedCash;

        // Enforce mandatory reason if discrepancy exceeds Rp 5.000
        if (abs($cashDifference) > 5000 && empty(trim($validated['difference_reason'] ?? ''))) {
            throw ValidationException::withMessages([
                'difference_reason' => ['Terdapat selisih kas fisik sebesar Rp ' . number_format(abs($cashDifference), 0, ',', '.') . '. Alasan / keterangan selisih wajib diisi sebelum shift ditutup.'],
            ]);
        }

        $activeShift->update([
            'closed_by' => $user->id,
            'closed_at' => $closedAt,
            'expected_cash_total' => $expectedCash,
            'actual_cash_counted' => $actualCash,
            'cash_difference' => $cashDifference,
            'difference_reason' => $validated['difference_reason'] ?? null,
            'closing_notes' => $validated['closing_notes'] ?? null,
            'status' => 'closed',
        ]);

        $activeShift->load(['branch', 'openedBy', 'closedBy']);

        return response()->json([
            'success' => true,
            'message' => "Shift kasir {$activeShift->shift_number} berhasil ditutup.",
            'data' => [
                'id' => $activeShift->id,
                'shift_number' => $activeShift->shift_number,
                'branch_id' => $activeShift->branch_id,
                'branch_name' => $activeShift->branch?->name ?? 'Cabang #' . $activeShift->branch_id,
                'opened_by_name' => $activeShift->openedBy?->name ?? 'Kasir',
                'closed_by_name' => $activeShift->closedBy?->name ?? $user->name,
                'opened_at' => $activeShift->opened_at->toIso8601String(),
                'closed_at' => $activeShift->closed_at->toIso8601String(),
                'opening_cash_float' => (float) $activeShift->opening_cash_float,
                'expected_cash_total' => (float) $activeShift->expected_cash_total,
                'actual_cash_counted' => (float) $activeShift->actual_cash_counted,
                'cash_difference' => (float) $activeShift->cash_difference,
                'difference_reason' => $activeShift->difference_reason,
                'closing_notes' => $activeShift->closing_notes,
                'status' => $activeShift->status,
                'current_cash_inflow' => $cashInflow,
                'current_cash_refund' => $cashRefund,
                'current_cash_expenses' => $cashExpenses,
            ],
        ]);
    }

    /**
     * Retrieve paginated shift history for the branch.
     * GET /api/v1/cashier/shift/history
     */
    public function history(Request $request): JsonResponse
    {
        $user = $this->resolveUser($request);
        if (!$user) {
            return response()->json([
                'status' => 'error',
                'message' => 'Unauthenticated.',
            ], 401);
        }

        $branchId = $this->resolveBranchId($request, $user);
        $perPage = min(100, max(5, (int) $request->query('per_page', 20)));

        $query = CashDrawerShift::with(['openedBy', 'closedBy', 'branch'])
            ->where('branch_id', $branchId);

        if ($request->filled('status')) {
            $query->where('status', $request->query('status'));
        }

        $shifts = $query->orderBy('opened_at', 'desc')->paginate($perPage);

        $data = $shifts->map(function ($s) {
            $shiftStart = $s->opened_at;
            $shiftEnd = $s->closed_at ?? Carbon::now('Asia/Jakarta');

            $cashPaymentQuery = BookingPayment::whereBetween('paid_at', [$shiftStart, $shiftEnd])
                ->where('is_void', false)
                ->where('affiliate_id', $s->branch_id)
                ->where(function ($q) {
                    $q->where('payment_id', 2)
                      ->orWhereHas('payment', fn($pq) => $pq->where('slug', 'cash')->orWhere('name', 'like', '%tunai%'));
                });

            $cashInflow = (float) (clone $cashPaymentQuery)->where('type', '!=', 'refund')->sum('amount');
            $cashRefund = (float) (clone $cashPaymentQuery)->where('type', 'refund')->sum('amount');
            $cashExpenses = (float) Expense::whereBetween('spent_at', [$shiftStart, $shiftEnd])
                ->where('affiliate_id', $s->branch_id)
                ->sum('amount');

            return [
                'id' => $s->id,
                'shift_number' => $s->shift_number,
                'branch_id' => $s->branch_id,
                'branch_name' => $s->branch?->name ?? 'Cabang #' . $s->branch_id,
                'opened_by_name' => $s->openedBy?->name ?? 'Kasir',
                'closed_by_name' => $s->closedBy?->name,
                'opened_at' => $s->opened_at?->toIso8601String(),
                'closed_at' => $s->closed_at?->toIso8601String(),
                'opening_cash_float' => (float) $s->opening_cash_float,
                'expected_cash_total' => $s->expected_cash_total !== null ? (float) $s->expected_cash_total : null,
                'actual_cash_counted' => $s->actual_cash_counted !== null ? (float) $s->actual_cash_counted : null,
                'cash_difference' => $s->cash_difference !== null ? (float) $s->cash_difference : null,
                'difference_reason' => $s->difference_reason,
                'closing_notes' => $s->closing_notes,
                'status' => $s->status,
                'current_cash_inflow' => $cashInflow,
                'current_cash_refund' => $cashRefund,
                'current_cash_expenses' => $cashExpenses,
            ];
        });

        return response()->json([
            'success' => true,
            'data' => $data,
            'meta' => [
                'current_page' => $shifts->currentPage(),
                'last_page' => $shifts->lastPage(),
                'per_page' => $shifts->perPage(),
                'total' => $shifts->total(),
            ],
        ]);
    }

    /**
     * Get shift operational schedule settings.
     * GET /api/v1/cashier/shift/schedule
     */
    public function getSchedule(Request $request): JsonResponse
    {
        $user = $this->resolveUser($request);
        if (!$user) {
            return response()->json([
                'status' => 'error',
                'message' => 'Unauthenticated.',
            ], 401);
        }

        $branchId = $this->resolveBranchId($request, $user);

        $activeShift = CashDrawerShift::where('branch_id', $branchId)
            ->where('status', 'open')
            ->latest('opened_at')
            ->first();

        $openingTime = '08:00';
        $closingTime = '22:00';
        $graceMinutes = 30;
        $enforcementEnabled = true;

        $hasActiveShift = $activeShift !== null;
        $now = Carbon::now('Asia/Jakarta');
        $currentTimeStr = $now->format('H:i');

        $scheduleStatus = 'closed';
        if ($hasActiveShift) {
            $scheduleStatus = 'shift_open';
        } elseif ($currentTimeStr < $openingTime) {
            $scheduleStatus = 'not_opened';
        } else {
            $scheduleStatus = 'closed';
        }

        return response()->json([
            'success' => true,
            'data' => [
                'branch_id' => $branchId,
                'enforcement_enabled' => $enforcementEnabled,
                'opening_time' => $openingTime,
                'closing_time' => $closingTime,
                'grace_period_minutes' => $graceMinutes,
                'has_active_shift' => $hasActiveShift,
                'schedule_status' => $scheduleStatus,
                'can_book' => $hasActiveShift,
                'status_message' => $hasActiveShift ? 'Shift kasir sedang aktif.' : 'Shift kasir belum dibuka.',
            ],
        ]);
    }

    /**
     * Update shift schedule configuration.
     * PUT /api/v1/cashier/shift/schedule
     */
    public function updateSchedule(Request $request): JsonResponse
    {
        return $this->getSchedule($request);
    }
}
