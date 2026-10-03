<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\ReceiptResource;
use App\Models\Booking;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReceiptController extends Controller
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

        // Staff: hanya boleh mengakses booking miliknya sendiri
        return $booking->user_id == $user->id;
    }

    /**
     * Display a paginated listing of completed & active rental transactions for receipt history.
     * GET /api/v1/receipts
     * GET /api/v1/receipts/history
     * GET /api/v1/transactions/completed
     */
    public function index(Request $request): JsonResponse
    {
        $query = Booking::with([
            'iphone',
            'payment',
            'user',
            'paymentTransactions.payment',
            'latestReturn',
            'affiliate',
        ]);

        $user = $this->resolveUser($request);
        if ($user) {
            $isSuperAdmin = method_exists($user, 'hasRole') && $user->hasRole('super-admin');
            $isAdmin = method_exists($user, 'hasRole') && $user->hasRole('admin');
            $isAffiliateAdmin = method_exists($user, 'hasRole') && $user->hasRole('affiliate-admin');
            $isStaff = ! $isSuperAdmin && ! $isAdmin && ! $isAffiliateAdmin;

            if ($isStaff) {
                // Staff: hanya riwayat struk booking miliknya sendiri
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

        // Filter by completed / operational transactions by default
        if ($status = $request->query('status')) {
            if ($status !== 'all' && $status !== 'semua') {
                $query->where('status', $status);
            }
        } else {
            $query->where(function ($q) {
                $q->whereIn('status', ['rented', 'returned', 'completed', 'confirmed'])
                    ->orWhereIn('payment_status', ['paid', 'partial', 'dp'])
                    ->orWhereHas('paymentTransactions');
            });
        }

        // Multi-field search query (?q=, ?search=, ?query=)
        if ($search = $request->query('q') ?? $request->query('search') ?? $request->query('query')) {
            $search = trim($search);
            $cleanSearch = preg_replace('/^REC-/i', '', $search);
            $query->where(function ($q) use ($search, $cleanSearch) {
                $q->where('booking_code', 'like', "%{$search}%")
                    ->orWhere('booking_code', 'like', "%{$cleanSearch}%")
                    ->orWhere('customer_name', 'like', "%{$search}%")
                    ->orWhere('customer_phone', 'like', "%{$search}%")
                    ->orWhereHas('iphone', function ($iq) use ($search) {
                        $iq->where('name', 'like', "%{$search}%")
                            ->orWhere('asset_code', 'like', "%{$search}%")
                            ->orWhere('serial_number', 'like', "%{$search}%");
                    });
            });
        }

        // Filter by receipt type (?type=)
        $requestedType = strtolower($request->query('type', ''));
        if ($requestedType && ! in_array($requestedType, ['all', 'semua'])) {
            if (in_array($requestedType, ['pickup'])) {
                $query->whereIn('status', ['confirmed', 'rented', 'returned']);
            } elseif (in_array($requestedType, ['return', 'returnunit', 'pengembalian'])) {
                $query->where(function ($q) {
                    $q->where('status', 'returned')
                        ->orWhereHas('latestReturn');
                });
            } elseif (in_array($requestedType, ['payment', 'paymentsettlement', 'pelunasan'])) {
                $query->where(function ($q) {
                    $q->where('payment_status', 'paid')
                        ->orWhereHas('paymentTransactions');
                });
            } elseif (in_array($requestedType, ['deposit', 'depositrefund', 'refund'])) {
                $query->whereHas('paymentTransactions', function ($pq) {
                    $pq->whereIn('type', ['refund', 'deposit']);
                });
            }
        }

        // Filter by date range (?start_date= & ?end_date= or ?date=)
        if ($date = $request->query('date')) {
            if ($date === 'today' || $date === 'hari_ini') {
                $query->where(function ($q) {
                    $q->whereDate('start_booking_date', Carbon::today())
                        ->orWhereDate('created', Carbon::today());
                });
            } else {
                $query->where(function ($q) use ($date) {
                    $q->whereDate('start_booking_date', $date)
                        ->orWhereDate('created', $date);
                });
            }
        }

        if ($startDate = $request->query('start_date')) {
            $parsedStart = Carbon::parse($startDate)->startOfDay();
            $query->where(function ($q) use ($parsedStart) {
                $q->where('start_booking_date', '>=', $parsedStart->toDateString())
                    ->orWhere('created', '>=', $parsedStart);
            });
        }

        if ($endDate = $request->query('end_date')) {
            $parsedEnd = Carbon::parse($endDate)->endOfDay();
            $query->where(function ($q) use ($parsedEnd) {
                $q->where('start_booking_date', '<=', $parsedEnd->toDateString())
                    ->orWhere('created', '<=', $parsedEnd);
            });
        }

        // Summary aggregates before pagination
        $summaryQuery = clone $query;
        $totalCount = $summaryQuery->count();
        $totalRentFee = (float) (clone $query)->sum('price');
        $totalDepositFee = 0.0;
        $totalPaid = $totalRentFee;

        // Sorting: newest bookings first
        $query->latest('id');

        // Pagination
        $perPage = min(100, max(1, (int) $request->query('per_page', 15)));
        $paginated = $query->paginate($perPage);

        $formattedData = collect($paginated->items())->map(function ($booking) use ($requestedType, $request) {
            $data = $this->formatReceiptData($booking, $requestedType, $request);
            return new ReceiptResource($data);
        });

        return response()->json([
            'status' => 'success',
            'data' => $formattedData,
            'meta' => [
                'current_page' => $paginated->currentPage(),
                'per_page' => $paginated->perPage(),
                'total' => $paginated->total(),
                'last_page' => $paginated->lastPage(),
                'from' => $paginated->firstItem(),
                'to' => $paginated->lastItem(),
            ],
            'summary' => [
                'total_transactions' => $totalCount,
                'total_rent_fee' => $totalRentFee,
                'total_deposit_fee' => $totalDepositFee,
                'total_amount' => $totalRentFee + $totalDepositFee,
                'total_paid' => $totalPaid,
            ],
        ]);
    }

    /**
     * Display the receipt transaction details formatted for thermal printing and digital viewing.
     * GET /api/v1/receipts/{bookingIdOrCode}
     * GET /api/v1/bookings/{idOrCode}/receipt
     */
    public function show(Request $request, string $bookingIdOrCode): JsonResponse
    {
        $booking = Booking::with([
            'iphone',
            'payment',
            'user',
            'paymentTransactions.payment',
            'latestReturn',
            'affiliate',
        ])
        ->where(function ($q) use ($bookingIdOrCode) {
            if (is_numeric($bookingIdOrCode)) {
                $q->where('id', (int) $bookingIdOrCode)
                    ->orWhere('booking_code', $bookingIdOrCode);
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
                'message' => 'Anda tidak memiliki hak akses untuk melihat struk transaksi ini.',
            ], 403);
        }

        $receiptData = $this->formatReceiptData($booking, $request->query('type'), $request);

        return response()->json([
            'status' => 'success',
            'data' => new ReceiptResource($receiptData),
        ]);
    }

    /**
     * Build structured receipt data array aligned with Flutter ReceiptModel & thermal printing.
     */
    public function formatReceiptData(Booking $booking, ?string $requestedType = null, ?Request $request = null): array
    {
        $typeParam = strtolower($requestedType ?? '');
        $receiptType = match ($typeParam) {
            'pickup' => 'pickup',
            'return', 'returnunit', 'pengembalian' => 'returnUnit',
            'payment', 'paymentsettlement', 'pelunasan' => 'paymentSettlement',
            'deposit', 'depositrefund', 'refund' => 'depositRefund',
            default => $this->detectReceiptType($booking),
        };

        $typeLabel = match ($receiptType) {
            'pickup' => 'PICKUP IPHONE',
            'returnUnit' => 'PENGEMBALIAN UNIT',
            'paymentSettlement' => 'PELUNASAN SEWA',
            'depositRefund' => 'REFUND DEPOSIT',
            default => 'BUKTI TRANSAKSI',
        };

        $date = now();
        $receiptNumber = 'REC-' . strtoupper(substr($booking->booking_code, 0, 8)) . '-' . date('ymd');

        // Admin & Branch
        $adminName = $request?->user()?->name ?? $booking->user?->name ?? 'Kasir SKYRental';
        $branchName = 'SKYRENTAL YOGYAKARTA';

        // Unit info
        $unitName = $booking->iphone?->name ?? 'iPhone Unit';
        $serialNumber = $booking->iphone?->serial_number;
        $assetCode = $booking->iphone?->asset_code;

        // Dates & Duration
        $durationHours = (int) ($booking->duration ?? 24);
        $durationLabel = $durationHours >= 24
            ? round($durationHours / 24) . ' Hari (' . $durationHours . ' Jam)'
            : $durationHours . ' Jam';

        $startDateFormatted = $booking->start_booking_date ? Carbon::parse($booking->start_booking_date)->format('d/m') : '';
        $endDateFormatted = $booking->end_booking_date ? Carbon::parse($booking->end_booking_date)->format('d/m/Y') : '';
        $rentalDates = ($startDateFormatted && $endDateFormatted) ? "{$startDateFormatted} - {$endDateFormatted}" : null;

        // Financial values
        $rentFee = (float) $booking->price;
        $depositFee = 0.0;
        $finesFee = (float) ($booking->latestReturn?->penalty_fee ?? 0);
        $discountFee = 0.0;

        // Total amount based on receipt context
        $totalAmount = match ($receiptType) {
            'pickup' => $rentFee,
            'paymentSettlement' => $rentFee,
            'depositRefund' => 0.0,
            'returnUnit' => $rentFee + $finesFee,
            default => $rentFee,
        };

        // Paid & remaining
        $totalRentalPaid = (float) $booking->paymentTransactions()
            ->whereIn('type', ['dp', 'payment', 'pelunasan'])
            ->sum('amount');
        if ($totalRentalPaid == 0 && $booking->payment_status === 'paid') {
            $totalRentalPaid = $rentFee;
        }

        $depositPaid = 0.0;
        $paidAmount = $totalRentalPaid;
        $remainingAmount = max(0, (float) ($rentFee - $totalRentalPaid));

        // Latest payment details
        $latestPayment = $booking->paymentTransactions()->latest('id')->first();
        $paymentMethod = $latestPayment?->payment?->name ?? $booking->payment?->name ?? 'Tunai (Cash)';
        $paymentStatus = strtoupper($booking->payment_status ?? 'paid');

        $cashGiven = (float) ($latestPayment?->pay ?? $paidAmount);
        $cashChange = (float) ($latestPayment?->change ?? 0);

        // Deposit status & refund
        $depositStatus = 'TIDAK ADA';
        $refundAmount = 0.0;

        $notes = match ($receiptType) {
            'pickup' => "Jaminan " . ($booking->jaminan_type ?? 'KTP Asli') . " fisik telah diverifikasi dan disimpan.",
            'returnUnit' => "Unit diterima kembali dalam kondisi baik. Pemeriksaan selesai.",
            'depositRefund' => "Uang jaminan deposit telah dikembalikan ke pelanggan.",
            default => "Terima kasih telah mempercayakan rental di SKYRental.",
        };

        $terms = 'Harap simpan struk ini sebagai bukti transaksi resmi.';

        $receiptData = [
            'receipt_number' => $receiptNumber,
            'date' => $date->toIso8601String(),
            'date_formatted' => $date->format('d M Y, H:i'),
            'admin_name' => $adminName,
            'branch_name' => $branchName,
            'type' => $receiptType,
            'type_label' => $typeLabel,
            'booking_code' => $booking->booking_code,
            'customer_name' => $booking->customer_name,
            'customer_phone' => $booking->customer_phone,
            'unit_name' => $unitName,
            'serial_number' => $serialNumber,
            'asset_code' => $assetCode,
            'rental_duration' => $durationLabel,
            'rental_dates' => $rentalDates,
            'rent_fee' => $rentFee,
            'deposit_fee' => $depositFee,
            'fines_fee' => $finesFee,
            'discount_fee' => $discountFee,
            'total_amount' => $totalAmount,
            'paid_amount' => $paidAmount,
            'remaining_amount' => $remainingAmount,
            'payment_method' => $paymentMethod,
            'payment_status' => $paymentStatus,
            'cash_given' => $cashGiven,
            'cash_change' => $cashChange,
            'deposit_status' => $depositStatus,
            'refund_amount' => $refundAmount,
            'notes' => $notes,
            'terms_and_conditions' => $terms,
        ];

        $receiptData['esc_pos_text'] = $this->generateEscPos32ColText($receiptData);

        return $receiptData;
    }

    private function detectReceiptType(Booking $booking): string
    {
        if ($booking->status === 'returned') {
            return 'returnUnit';
        }
        if ($booking->status === 'rented') {
            return 'pickup';
        }
        if ($booking->payment_status === 'paid') {
            return 'paymentSettlement';
        }
        return 'pickup';
    }

    private function generateEscPos32ColText(array $d): string
    {
        $w = 32;
        $div = str_repeat('=', $w);
        $sub = str_repeat('-', $w);

        $lines = [];
        $lines[] = $this->center('SKYRENTAL IPHONE', $w);
        $lines[] = $this->center($d['branch_name'] ?? 'SKYRENTAL YOGYAKARTA', $w);
        $lines[] = $this->center('Rental iPhone Terbaik & Terpercaya', $w);
        $lines[] = $div;

        $lines[] = $this->twoCol('No. Resi  :', $d['receipt_number'] ?? '-', $w);
        $lines[] = $this->twoCol('Tanggal   :', $d['date_formatted'] ?? date('d/m/Y H:i'), $w);
        $lines[] = $this->twoCol('Kasir     :', $d['admin_name'] ?? 'Admin', $w);
        $lines[] = $this->twoCol('Tipe      :', $d['type_label'] ?? 'STRUK', $w);
        $lines[] = $sub;

        $lines[] = $this->twoCol('Pelanggan :', $d['customer_name'] ?? '-', $w);
        $lines[] = $this->twoCol('Kontak    :', $d['customer_phone'] ?? '-', $w);
        $lines[] = $this->twoCol('Kode Book :', $d['booking_code'] ?? '-', $w);
        $lines[] = $this->twoCol('Unit      :', $d['unit_name'] ?? '-', $w);
        if (! empty($d['asset_code'])) {
            $lines[] = $this->twoCol('No. Aset  :', $d['asset_code'], $w);
        }
        if (! empty($d['serial_number'])) {
            $lines[] = $this->twoCol('Serial No :', $d['serial_number'], $w);
        }
        $lines[] = $this->twoCol('Durasi    :', $d['rental_duration'] ?? '-', $w);
        $lines[] = $sub;

        $lines[] = $this->twoCol('Biaya Sewa:', 'Rp ' . number_format($d['rent_fee'] ?? 0, 0, ',', '.'), $w);
        $lines[] = $this->twoCol('Deposit   :', 'Rp ' . number_format($d['deposit_fee'] ?? 0, 0, ',', '.'), $w);
        if (($d['fines_fee'] ?? 0) > 0) {
            $lines[] = $this->twoCol('Denda/Rusak:', 'Rp ' . number_format($d['fines_fee'], 0, ',', '.'), $w);
        }
        $lines[] = $sub;

        $lines[] = $this->twoCol('TOTAL     :', 'Rp ' . number_format($d['total_amount'] ?? 0, 0, ',', '.'), $w);
        $lines[] = $this->twoCol('Terbayar  :', 'Rp ' . number_format($d['paid_amount'] ?? 0, 0, ',', '.'), $w);
        if (($d['remaining_amount'] ?? 0) > 0) {
            $lines[] = $this->twoCol('Sisa Tagih:', 'Rp ' . number_format($d['remaining_amount'], 0, ',', '.'), $w);
        }
        if (($d['refund_amount'] ?? 0) > 0) {
            $lines[] = $this->twoCol('Refund Dep:', 'Rp ' . number_format($d['refund_amount'], 0, ',', '.'), $w);
        }
        $lines[] = $this->twoCol('Metode    :', $d['payment_method'] ?? 'Tunai', $w);
        $lines[] = $this->twoCol('Status    :', $d['payment_status'] ?? 'LUNAS', $w);
        $lines[] = $div;

        $lines[] = $this->center('Harap simpan bukti transaksi', $w);
        $lines[] = $this->center('CS: 0812-3456-7890 (WA)', $w);
        $lines[] = $div;

        return implode("\n", $lines);
    }

    private function center(string $text, int $width = 32): string
    {
        if (strlen($text) >= $width) {
            return substr($text, 0, $width);
        }
        $left = (int) floor(($width - strlen($text)) / 2);
        $right = $width - strlen($text) - $left;
        return str_repeat(' ', $left) . $text . str_repeat(' ', $right);
    }

    private function twoCol(string $left, string $right, int $width = 32): string
    {
        if (strlen($right) >= $width) {
            $right = substr($right, 0, $width - 1);
        }
        if (strlen($left) + strlen($right) + 1 > $width) {
            $maxLeft = $width - strlen($right) - 1;
            $left = substr($left, 0, max(0, $maxLeft));
        }
        $spaces = $width - strlen($left) - strlen($right);
        return $left . str_repeat(' ', max(1, $spaces)) . $right;
    }
}
