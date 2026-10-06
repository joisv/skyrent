<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\BookingPaymentResource;
use App\Http\Resources\BookingResource;
use App\Models\Booking;
use App\Models\BookingPayment;
use App\Models\Payment;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Laravel\Sanctum\PersonalAccessToken;

class PaymentController extends Controller
{
    protected function resolveUser(Request $request): ?User
    {
        $user = $request->user('sanctum') ?? $request->user();
        if (! $user && $bearer = $request->bearerToken()) {
            $tokenModel = PersonalAccessToken::findToken($bearer);
            if ($tokenModel && $tokenModel->tokenable instanceof User) {
                $user = $tokenModel->tokenable;
            }
        }
        return $user;
    }

    protected function canAccessBooking(?Booking $booking, ?User $user): bool
    {
        if (! $booking || ! $user) {
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
     * Display a listing of payment history with multi-field search and filters.
     * GET /api/v1/payments
     * GET /api/v1/bookings/{idOrCode}/payments
     */
    public function index(Request $request, ?string $idOrCode = null): JsonResponse
    {
        $query = BookingPayment::with(['booking', 'payment', 'user']);

        // Scope by user role
        $authUser = $this->resolveUser($request);
        if ($authUser) {
            if (method_exists($authUser, 'hasRole') && $authUser->hasRole('super-admin')) {
                // super-admin: melihat semua pembayaran dari semua user dan semua affiliate
            } elseif (method_exists($authUser, 'hasRole') && $authUser->hasRole('admin')) {
                if ($authUser->affiliate_id) {
                    $query->whereHas('booking', function ($bq) use ($authUser) {
                        $bq->where('affiliate_id', $authUser->affiliate_id)
                            ->orWhere('user_id', $authUser->id);
                    });
                }
            } elseif (method_exists($authUser, 'hasRole') && $authUser->hasRole('affiliate-admin')) {
                if ($authUser->affiliate_id) {
                    $query->whereHas('booking', function ($bq) use ($authUser) {
                        $bq->where('affiliate_id', $authUser->affiliate_id)
                            ->orWhere('user_id', $authUser->id);
                    });
                } else {
                    $query->whereHas('booking', function ($bq) use ($authUser) {
                        $bq->where('user_id', $authUser->id);
                    });
                }
            } else {
                // staff: hanya transaksi dari booking yang dibuat oleh dirinya sendiri
                $query->whereHas('booking', function ($bq) use ($authUser) {
                    $bq->where('user_id', $authUser->id);
                });
            }
        }

        // Scope to booking if idOrCode or booking_code/id provided
        $bookingFilter = $idOrCode ?? $request->query('booking_code') ?? $request->query('booking_id');
        if ($bookingFilter) {
            $query->whereHas('booking', function ($bq) use ($bookingFilter) {
                if (is_numeric($bookingFilter)) {
                    $bq->where('id', (int) $bookingFilter)
                        ->orWhere('booking_code', $bookingFilter);
                } else {
                    $bq->where('booking_code', $bookingFilter);
                }
            });
        }

        // Multi-field search term (?q= or ?search=)
        if ($search = $request->query('q') ?? $request->query('search')) {
            $query->where(function ($q) use ($search) {
                if (is_numeric($search)) {
                    $q->where('id', (int) $search);
                }
                $q->orWhere('note', 'like', "%{$search}%")
                    ->orWhereHas('booking', function ($bq) use ($search) {
                        $bq->where('booking_code', 'like', "%{$search}%")
                            ->orWhere('customer_name', 'like', "%{$search}%")
                            ->orWhere('customer_phone', 'like', "%{$search}%");
                    });
            });
        }

        // Filter by type (?type=)
        if ($type = $request->query('type')) {
            if ($type === 'rental') {
                $query->whereIn('type', ['dp', 'payment', 'pelunasan', 'extend']);
            } elseif ($type !== 'all' && $type !== 'semua') {
                $query->where('type', $type);
            }
        }

        // Filter by payment method (?payment_method= or ?method= or ?payment_id=)
        if ($method = $request->query('payment_method') ?? $request->query('method')) {
            if (is_numeric($method)) {
                $query->where('payment_id', (int) $method);
            } else {
                $query->whereHas('payment', function ($pq) use ($method) {
                    $pq->where('slug', strtolower($method))
                        ->orWhere('name', 'like', "%{$method}%");
                });
            }
        } elseif ($paymentId = $request->query('payment_id')) {
            $query->where('payment_id', (int) $paymentId);
        }

        // Filter by date range (?start_date= & ?end_date= or ?date=)
        if ($singleDate = $request->query('date')) {
            $query->whereDate('paid_at', $singleDate);
        } else {
            if ($startDate = $request->query('start_date')) {
                $query->whereDate('paid_at', '>=', $startDate);
            }
            if ($endDate = $request->query('end_date')) {
                $query->whereDate('paid_at', '<=', $endDate);
            }
        }

        // Calculate summary aggregates on filtered results before pagination
        $summaryQuery = clone $query;
        $allResults = $summaryQuery->get();

        $totalIncome = (float) $allResults->whereIn('type', ['dp', 'payment', 'pelunasan', 'extend', 'penalty'])->sum('amount');
        $totalRefund = (float) $allResults->whereIn('type', ['refund'])->sum('amount');
        $netAmount = $totalIncome - $totalRefund;

        // Ordering & Pagination
        $query->orderBy('paid_at', 'desc')->orderBy('id', 'desc');

        $limit = min((int) $request->query('limit', 20), 100);
        $paginated = $query->paginate($limit);

        return response()->json([
            'status' => 'success',
            'total' => $paginated->total(),
            'current_page' => $paginated->currentPage(),
            'last_page' => $paginated->lastPage(),
            'per_page' => $paginated->perPage(),
            'summary' => [
                'total_transactions' => $paginated->total(),
                'total_income' => $totalIncome,
                'total_refund' => $totalRefund,
                'net_amount' => $netAmount,
            ],
            'data' => BookingPaymentResource::collection($paginated->items()),
        ]);
    }

    /**
     * Display a single payment transaction detail.
     * GET /api/v1/payments/{idOrCode}
     */
    public function show(Request $request, string $idOrCode): JsonResponse
    {
        $payment = BookingPayment::with(['booking', 'payment', 'user'])
            ->where(function ($q) use ($idOrCode) {
                if (is_numeric($idOrCode)) {
                    $q->where('id', (int) $idOrCode);
                } else {
                    $q->whereHas('booking', function ($bq) use ($idOrCode) {
                        $bq->where('booking_code', $idOrCode);
                    });
                }
            })
            ->first();

        if (! $payment) {
            return response()->json([
                'status' => 'error',
                'message' => "Transaksi pembayaran '{$idOrCode}' tidak ditemukan.",
            ], 404);
        }

        $authUser = $this->resolveUser($request);
        if ($authUser && $payment->booking && ! $this->canAccessBooking($payment->booking, $authUser)) {
            return response()->json([
                'status' => 'error',
                'message' => 'Anda tidak memiliki hak akses untuk melihat transaksi pembayaran ini.',
            ], 403);
        }

        return response()->json([
            'status' => 'success',
            'data' => new BookingPaymentResource($payment),
        ]);
    }

    /**
     * Record a rental payment (DP / Pelunasan) for a booking and update its status.
     * POST /api/v1/bookings/{idOrCode}/payments
     * POST /api/v1/payments/rental
     */
    public function storeRentalPayment(Request $request, ?string $idOrCode = null): JsonResponse
    {
        $targetIdOrCode = $idOrCode ?? $request->input('booking_code') ?? $request->input('booking_id');

        if (! $targetIdOrCode) {
            return response()->json([
                'status' => 'error',
                'message' => 'Parameter booking_code atau booking_id wajib disertakan.',
            ], 422);
        }

        $booking = Booking::with(['paymentTransactions', 'iphone', 'payment', 'user'])
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

        $authUser = $this->resolveUser($request);
        if ($authUser && ! $this->canAccessBooking($booking, $authUser)) {
            return response()->json([
                'status' => 'error',
                'message' => 'Anda tidak memiliki hak akses untuk memproses pembayaran booking ini.',
            ], 403);
        }

        if ($booking->status === 'cancelled') {
            return response()->json([
                'status' => 'error',
                'message' => 'Tidak dapat melakukan pembayaran untuk booking yang telah dibatalkan.',
            ], 422);
        }

        $amount = (float) $request->input('amount', 0);
        if ($amount <= 0) {
            return response()->json([
                'status' => 'error',
                'message' => 'Nominal pembayaran (amount) harus lebih besar dari 0.',
            ], 422);
        }

        $requestedType = $request->input('type');
        $isExtendPayment = ($requestedType === 'extend');

        $currentPaid = (float) $booking->paymentTransactions()
            ->whereIn('type', ['dp', 'payment', 'pelunasan', 'extend'])
            ->sum('amount');
        $currentRemaining = max(0, (float) $booking->price - $currentPaid);

        if (! $isExtendPayment && $currentRemaining <= 0) {
            return response()->json([
                'status' => 'error',
                'message' => 'Tagihan sewa untuk booking ini sudah lunas (Rp ' . number_format($booking->price, 0, ',', '.') . ').',
            ], 422);
        }

        $pay = $request->has('pay') ? (float) $request->input('pay') : $amount;
        if ($pay < $amount) {
            return response()->json([
                'status' => 'error',
                'message' => 'Jumlah uang yang dibayarkan (pay) tidak boleh lebih kecil dari tagihan (amount).',
            ], 422);
        }

        $change = max(0, $pay - $amount);

        // Resolve payment method
        $paymentMethodInput = $request->input('payment_id') ?? $request->input('payment_method') ?? $request->input('method');
        $payment = null;

        if ($paymentMethodInput) {
            if (is_numeric($paymentMethodInput)) {
                $payment = Payment::find($paymentMethodInput);
            } else {
                $payment = Payment::where('slug', strtolower($paymentMethodInput))
                    ->orWhere('name', 'like', "%{$paymentMethodInput}%")
                    ->first();
            }
        }

        if (! $payment) {
            $payment = $booking->payment ?? Payment::first();
        }

        $paymentId = $payment?->id ?? $booking->payment_id;

        if (! $paymentId) {
            $defaultPayment = Payment::firstOrCreate(
                ['slug' => 'cash'],
                ['name' => 'Tunai (Cash)', 'is_active' => true]
            );
            $paymentId = $defaultPayment->id;
            $payment = $defaultPayment;
        }

        // Determine payment type
        $requestedType = $request->input('type');
        if ($requestedType && in_array($requestedType, ['dp', 'payment', 'pelunasan', 'extend', 'penalty'])) {
            $type = $requestedType;
        } else {
            $type = ($currentPaid + $amount >= (float) $booking->price) ? 'payment' : ($currentPaid == 0 ? 'dp' : 'payment');
        }

        $userId = $authUser?->id ?? $request->user()?->id ?? $request->input('user_id');

        // Prevent duplicate payment creation (e.g. repeated double clicks within 15 seconds with identical booking, amount, and type)
        $recentDuplicate = BookingPayment::where('booking_id', $booking->id)
            ->where('amount', $amount)
            ->where('type', $type)
            ->where('created_at', '>=', now()->subSeconds(15))
            ->first();

        if ($recentDuplicate) {
            return response()->json([
                'status' => 'success',
                'message' => 'Pembayaran berhasil diproses.',
                'data' => new BookingPaymentResource($recentDuplicate),
            ], 200);
        }

        $paymentRecord = BookingPayment::create([
            'booking_id' => $booking->id,
            'payment_id' => $paymentId,
            'amount' => $amount,
            'pay' => $pay,
            'change' => $change,
            'type' => $type,
            'paid_at' => $request->filled('paid_at') ? Carbon::parse($request->input('paid_at')) : now(),
            'user_id' => $userId,
            'note' => $request->input('note') ?? $request->input('notes'),
        ]);

        // Recalculate financial totals on booking
        $newTotalPaid = (float) $booking->paymentTransactions()
            ->whereIn('type', ['dp', 'payment', 'pelunasan'])
            ->sum('amount');
        $newRemaining = max(0, (float) $booking->price - $newTotalPaid);
        $newPaymentStatus = ($newTotalPaid >= (float) $booking->price)
            ? 'paid'
            : ($newTotalPaid > 0 ? 'partial' : 'unpaid');

        $booking->payment_status = $newPaymentStatus;
        if (! $booking->payment_id && $paymentId) {
            $booking->payment_id = $paymentId;
        }
        $booking->save();
        $booking->updatePaymentStatus();

        // Kirim WhatsApp bukti pembayaran jika send_whatsapp aktif
        $sendWhatsapp = $request->boolean('send_whatsapp', true);
        $whatsappToken = config('services.fonnte.token');
        if ($sendWhatsapp && $whatsappToken && !empty($booking->customer_phone)) {
            try {
                $statusText = $newPaymentStatus === 'paid' ? '*LUNAS*' : '*DP / SEBAGIAN*';
                $iphoneName = $booking->iphone ? "{$booking->iphone->name} {$booking->iphone->serial_number}" : 'Unit iPhone';
                $waMessage = "Halo {$booking->customer_name},\n\n"
                    . "Pembayaran rental Anda di *SkyRental* telah berhasil dicatat.\n\n"
                    . "Detail Pembayaran:\n"
                    . "--------------------------------------\n"
                    . "Kode Booking : *{$booking->booking_code}*\n"
                    . "Perangkat    : {$iphoneName}\n"
                    . "Nominal Bayar: Rp " . number_format($amount, 0, ',', '.') . "\n"
                    . "Metode Bayar : " . ($payment?->name ?? 'Tunai (Cash)') . "\n"
                    . "Status Bayar : {$statusText}\n"
                    . "Sisa Tagihan : Rp " . number_format($newRemaining, 0, ',', '.') . "\n"
                    . "Waktu        : " . $paymentRecord->paid_at->format('d/m/Y H:i') . " WIB\n"
                    . "--------------------------------------\n\n"
                    . "Untuk memeriksa status booking, silakan kunjungi:\n"
                    . url('/booking-status') . "\n\n"
                    . "Terima kasih telah mempercayakan sewa iPhone kepada SkyRental.\n\n"
                    . "Salam,\n*SkyRental*";

                Http::timeout(10)->withHeaders([
                    'Authorization' => $whatsappToken,
                ])->post('https://api.fonnte.com/send', [
                    'target' => $this->formatPhoneNumber($booking->customer_phone),
                    'message' => $waMessage,
                ]);
            } catch (\Exception $e) {
                logger()->error('Fonnte Payment Notification Error: ' . $e->getMessage());
            }
        }

        // Kirim notifikasi Telegram ke Admin
        $telegramToken = config('services.telegram.bot_token');
        $chatId = config('services.telegram.chat_id');
        if ($telegramToken && $chatId) {
            try {
                $statusText = $newPaymentStatus === 'paid' ? 'LUNAS' : 'SEBAGIAN';
                $tgMessage = "<b>Pembayaran Baru Diterima</b>\n\n"
                    . "<b>Kode Booking</b>: {$booking->booking_code}\n"
                    . "<b>Customer</b>    : {$booking->customer_name}\n"
                    . "<b>Nominal</b>     : Rp " . number_format($amount, 0, ',', '.') . "\n"
                    . "<b>Metode</b>      : " . ($payment?->name ?? 'Tunai (Cash)') . "\n"
                    . "<b>Status</b>      : {$statusText}\n"
                    . "<b>Sisa Tagihan</b>: Rp " . number_format($newRemaining, 0, ',', '.') . "\n\n"
                    . "🔗 <a href='" . url('/admin/bookings/' . $booking->id) . "'>Lihat detail di Admin Panel</a>";

                Http::timeout(10)->post("https://api.telegram.org/bot{$telegramToken}/sendMessage", [
                    'chat_id' => $chatId,
                    'text' => $tgMessage,
                    'parse_mode' => 'HTML',
                ]);
            } catch (\Exception $e) {
                logger()->error('Telegram Payment Notification Error: ' . $e->getMessage());
            }
        }

        $freshBooking = $booking->fresh(['paymentTransactions.payment', 'iphone', 'payment', 'user']);

        return response()->json([
            'status' => 'success',
            'message' => 'Pembayaran sewa sebesar Rp ' . number_format($amount, 0, ',', '.') . ' berhasil dicatat.',
            'data' => new BookingResource($freshBooking),
            'payment' => new BookingPaymentResource($paymentRecord->fresh(['booking', 'payment', 'user'])),
            'payment_summary' => [
                'booking_code' => $booking->booking_code,
                'customer_name' => $booking->customer_name,
                'price' => (float) $booking->price,
                'amount_paid' => $amount,
                'cash_received' => $pay,
                'change' => $change,
                'payment_method' => $payment?->name ?? 'Tunai (Cash)',
                'total_paid' => $newTotalPaid,
                'remaining_payment' => $newRemaining,
                'payment_status' => $newPaymentStatus,
                'is_settled' => $newPaymentStatus === 'paid',
                'paid_at' => $paymentRecord->paid_at->toIso8601String(),
                'paid_at_formatted' => $paymentRecord->paid_at->format('d M Y, H:i'),
            ],
        ], 201);
    }

    /**
     * Deposit endpoints - Disabled since deposit feature was removed.
     */
    public function storeDeposit(Request $request, ?string $idOrCode = null): JsonResponse
    {
        return response()->json([
            'status' => 'success',
            'message' => 'Fitur uang jaminan (deposit) dinonaktifkan.',
            'data' => null,
        ]);
    }

    public function showDeposit(Request $request, string $idOrCode): JsonResponse
    {
        return response()->json([
            'status' => 'success',
            'has_deposit' => false,
            'data' => null,
            'deposit_status' => 'none',
            'deposit_status_label' => 'Tanpa Deposit',
            'nominal_standard' => 0,
            'can_receive_deposit' => false,
            'message' => 'Fitur deposit tidak aktif.',
        ]);
    }

    public function refundDeposit(Request $request, ?string $idOrCode = null): JsonResponse
    {
        return response()->json([
            'status' => 'success',
            'message' => 'Fitur pengembalian uang jaminan (refund deposit) dinonaktifkan.',
            'data' => null,
        ]);
    }

    /**
     * Get active payment methods for checkout and POS.
     * GET /api/v1/payment-methods
     */
    public function methods(): JsonResponse
    {
        $payments = Payment::where('is_active', true)
            ->orderBy('id', 'asc')
            ->get()
            ->map(function ($p) {
                return [
                    'id' => $p->id,
                    'name' => $p->name,
                    'slug' => $p->slug ?: Str::slug($p->name),
                    'icon' => $p->icon ? (Str::startsWith($p->icon, 'http') ? $p->icon : asset('storage/' . $p->icon)) : null,
                    'description' => $p->description,
                    'is_active' => (bool) $p->is_active,
                ];
            });

        return response()->json([
            'status' => 'success',
            'data' => $payments,
        ]);
    }

    /**
     * Normalisasi nomor telepon ke format WhatsApp Indonesia (+62/62)
     */
    public function formatPhoneNumber($phone, $mode = '62'): string
    {
        $digits = preg_replace('/\D/', '', (string) $phone);

        if (substr($digits, 0, 2) === '62') {
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
}
