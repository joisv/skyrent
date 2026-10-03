<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\BookingResource;
use App\Models\Booking;
use App\Models\BookingPayment;
use App\Models\Iphones;
use App\Models\Payment;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
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
     * Operational daily dashboard summary.
     * GET /api/v1/dashboard/summary
     * GET /api/v1/dashboard/daily
     */
    public function dailySummary(Request $request): JsonResponse
    {
        $timezone = 'Asia/Jakarta';
        $dateParam = $request->query('date');
        $now = Carbon::now($timezone);

        $targetDate = $dateParam ? Carbon::parse($dateParam, $timezone) : $now->copy();
        $todayDate = $targetDate->toDateString();

        // Affiliate and role scoping check based on authenticated user or request
        $user = $this->resolveUser($request);
        $isSuperAdmin = $user && method_exists($user, 'hasRole') && $user->hasRole('super-admin');
        $isAffiliateAdmin = $user && method_exists($user, 'hasRole') && $user->hasRole('affiliate-admin');
        $isAdmin = $user && method_exists($user, 'hasRole') && $user->hasRole('admin');
        $isStaff = $user && ! $isSuperAdmin && ! $isAdmin && ! $isAffiliateAdmin;

        $affiliateId = null;
        if ($isAffiliateAdmin) {
            $affiliateId = $user->affiliate_id;
        } elseif ($isAdmin && $user->affiliate_id) {
            $affiliateId = $user->affiliate_id;
        } else {
            $affiliateId = $request->query('affiliate_id');
        }

        // 1. Operational booking metrics & real-time inventory calculation
        $iphonesQuery = Iphones::query();
        if ($isAffiliateAdmin) {
            if ($affiliateId) {
                $iphonesQuery->where('affiliate_id', $affiliateId);
            } else {
                $iphonesQuery->whereRaw('1 = 0');
            }
        } elseif ($affiliateId) {
            $iphonesQuery->where('affiliate_id', $affiliateId);
        }
        $iphones = $iphonesQuery->with(['bookings' => function ($bq) {
            $bq->whereIn('status', ['confirmed', 'rented', 'disewa'])
               ->whereDoesntHave('returns')
               ->orderBy('end_booking_date', 'desc')
               ->orderBy('end_time', 'desc');
        }])->get();

        $totalUnits = $iphones->count();
        $tersediaUnits = 0;
        $disewaUnits = 0;
        $maintenanceUnits = 0;

        foreach ($iphones as $unit) {
            $rawStatus = strtolower($unit->status ?? 'ready');
            if (in_array($rawStatus, ['maintenance', 'perawatan'])) {
                $maintenanceUnits++;
                continue;
            }

            $unitStatus = 'tersedia';
            if ($rawStatus === 'rented' || $rawStatus === 'disewa') {
                $unitStatus = 'disewa';
            }

            foreach ($unit->bookings as $b) {
                if (! in_array(strtolower($b->status ?? ''), ['rented', 'disewa'])) {
                    continue;
                }
                $startTime = $b->start_time ? substr($b->start_time, 0, 5) : '00:00';
                $endTime = $b->end_time ? substr($b->end_time, 0, 5) : '23:59';
                $startDt = Carbon::parse($b->start_booking_date . ' ' . $startTime, $timezone);
                $endDt = Carbon::parse($b->end_booking_date . ' ' . $endTime, $timezone);

                if ($now->greaterThanOrEqualTo($startDt) && $now->lessThanOrEqualTo($endDt)) {
                    $unitStatus = 'disewa';
                    break;
                }
            }

            if ($unitStatus === 'disewa') {
                $disewaUnits++;
            } else {
                $tersediaUnits++;
            }
        }

        $availableUnits = $tersediaUnits;
        $rentedUnits = $disewaUnits;

        // Active rentals (sedang disewa)
        $activeRentalsQuery = Booking::whereIn('status', ['rented', 'disewa']);
        if ($isStaff) {
            $activeRentalsQuery->where('user_id', $user->id);
        } elseif ($isAffiliateAdmin) {
            if ($affiliateId) {
                $activeRentalsQuery->where(function ($q) use ($affiliateId, $user) {
                    $q->where('affiliate_id', $affiliateId)->orWhere('user_id', $user->id);
                });
            } else {
                $activeRentalsQuery->where('user_id', $user->id);
            }
        } elseif ($affiliateId) {
            $activeRentalsQuery->where('affiliate_id', $affiliateId);
        }
        $activeRentals = $activeRentalsQuery->count();

        // Overdue returns (melewati jadwal pengembalian)
        $overdueReturnsQuery = Booking::whereIn('status', ['rented', 'disewa'])
            ->where(function ($sub) use ($todayDate, $now) {
                $sub->where('end_booking_date', '<', $todayDate)
                    ->orWhere(function ($timeSub) use ($todayDate, $now) {
                        $timeSub->where('end_booking_date', $todayDate)
                            ->whereNotNull('end_time')
                            ->where('end_time', '<', $now->format('H:i'));
                    });
            });
        if ($isStaff) {
            $overdueReturnsQuery->where('user_id', $user->id);
        } elseif ($isAffiliateAdmin) {
            if ($affiliateId) {
                $overdueReturnsQuery->where(function ($q) use ($affiliateId, $user) {
                    $q->where('affiliate_id', $affiliateId)->orWhere('user_id', $user->id);
                });
            } else {
                $overdueReturnsQuery->where('user_id', $user->id);
            }
        } elseif ($affiliateId) {
            $overdueReturnsQuery->where('affiliate_id', $affiliateId);
        }
        $overdueReturns = $overdueReturnsQuery->count();
        $unreturnedUnits = $overdueReturns;

        // Returns due today (jadwal kembali hari ini)
        $todayReturnsQuery = Booking::whereIn('status', ['rented', 'disewa'])->whereDate('end_booking_date', $todayDate);
        if ($isStaff) {
            $todayReturnsQuery->where('user_id', $user->id);
        } elseif ($isAffiliateAdmin) {
            if ($affiliateId) {
                $todayReturnsQuery->where(function ($q) use ($affiliateId, $user) {
                    $q->where('affiliate_id', $affiliateId)->orWhere('user_id', $user->id);
                });
            } else {
                $todayReturnsQuery->where('user_id', $user->id);
            }
        } elseif ($affiliateId) {
            $todayReturnsQuery->where('affiliate_id', $affiliateId);
        }
        $todayReturns = $todayReturnsQuery->count();

        // Booking Hari Ini: Booking yang dibuat pada hari ini (created_at = todayDate)
        $bookingTodayQuery = Booking::whereDate('created_at', $todayDate);
        if ($isStaff) {
            $bookingTodayQuery->where('user_id', $user->id);
        } elseif ($isAffiliateAdmin) {
            if ($affiliateId) {
                $bookingTodayQuery->where(function ($q) use ($affiliateId, $user) {
                    $q->where('affiliate_id', $affiliateId)->orWhere('user_id', $user->id);
                });
            } else {
                $bookingTodayQuery->where('user_id', $user->id);
            }
        } elseif ($affiliateId) {
            $bookingTodayQuery->where('affiliate_id', $affiliateId);
        }
        $bookingToday = $bookingTodayQuery->count();

        // Pendapatan Hari Ini: Total pembayaran BookingPayment dengan paid_at = todayDate
        // STRICT: If no transactions today, revenue is 0.0 (NO fallback to previous days)
        $revenueTodayQuery = BookingPayment::whereDate('paid_at', $todayDate);
        if ($isStaff) {
            $revenueTodayQuery->whereHas('booking', fn($q) => $q->where('user_id', $user->id));
        } elseif ($isAffiliateAdmin) {
            if ($affiliateId) {
                $revenueTodayQuery->whereHas('booking', fn($q) => $q->where('affiliate_id', $affiliateId)->orWhere('user_id', $user->id));
            } else {
                $revenueTodayQuery->whereHas('booking', fn($q) => $q->where('user_id', $user->id));
            }
        } elseif ($affiliateId) {
            $revenueTodayQuery->whereHas('booking', fn($q) => $q->where('affiliate_id', $affiliateId));
        }
        $revenueToday = (float) $revenueTodayQuery->sum('amount');

        $todayPickupsQuery = Booking::where('status', 'confirmed')
            ->whereDate('start_booking_date', $todayDate);
        if ($isStaff) {
            $todayPickupsQuery->where('user_id', $user->id);
        } elseif ($isAffiliateAdmin) {
            if ($affiliateId) {
                $todayPickupsQuery->where(function ($q) use ($affiliateId, $user) {
                    $q->where('affiliate_id', $affiliateId)->orWhere('user_id', $user->id);
                });
            } else {
                $todayPickupsQuery->where('user_id', $user->id);
            }
        } elseif ($affiliateId) {
            $todayPickupsQuery->where('affiliate_id', $affiliateId);
        }
        $todayPickups = $todayPickupsQuery->count();

        $utilizationRate = $totalUnits > 0 ? (int) round(($rentedUnits / $totalUnits) * 100) : 0;

        // 3. Operational financials
        $revenueFromPayments = $revenueToday;
        $totalHeldDeposit = 0;
        $totalRefundedDeposit = 0;

        // 4. Action Items (Urgent attention)
        $actionItemsQuery = Booking::with(['iphone', 'payment', 'user']);
        if ($isStaff) {
            $actionItemsQuery->where('user_id', $user->id);
        } elseif ($isAffiliateAdmin) {
            if ($affiliateId) {
                $actionItemsQuery->where(function ($q) use ($affiliateId, $user) {
                    $q->where('affiliate_id', $affiliateId)->orWhere('user_id', $user->id);
                });
            } else {
                $actionItemsQuery->where('user_id', $user->id);
            }
        } elseif ($affiliateId) {
            $actionItemsQuery->where('affiliate_id', $affiliateId);
        }
        $actionItems = $actionItemsQuery
            ->where(function ($q) use ($todayDate, $now) {
                // Overdue
                $q->where(function ($overdue) use ($todayDate, $now) {
                    $overdue->whereIn('status', ['rented', 'disewa'])
                        ->where(function ($sub) use ($todayDate, $now) {
                            $sub->where('end_booking_date', '<', $todayDate)
                                ->orWhere(function ($timeSub) use ($todayDate, $now) {
                                    $timeSub->where('end_booking_date', $todayDate)
                                        ->whereNotNull('end_time')
                                        ->where('end_time', '<', $now->format('H:i'));
                                });
                        });
                })
                // Returns due today
                ->orWhere(function ($dueToday) use ($todayDate) {
                    $dueToday->whereIn('status', ['rented', 'disewa'])
                        ->whereDate('end_booking_date', $todayDate);
                })
                // Pickups scheduled for today
                ->orWhere(function ($pickupToday) use ($todayDate) {
                    $pickupToday->where('status', 'confirmed')
                        ->whereDate('start_booking_date', $todayDate);
                })
                // Pending confirmations
                ->orWhere('status', 'pending');
            })
            ->orderByRaw("CASE
                WHEN status IN ('rented', 'disewa') AND end_booking_date < '{$todayDate}' THEN 1
                WHEN status IN ('rented', 'disewa') AND end_booking_date = '{$todayDate}' THEN 2
                WHEN status = 'confirmed' AND start_booking_date = '{$todayDate}' THEN 3
                WHEN status = 'pending' THEN 4
                ELSE 5
            END ASC")
            ->take(15)
            ->get();

        // 5. All Bookings for transaction queue table matching booking-page.blade.php
        $allBookingsQuery = Booking::with(['iphone', 'payment', 'user'])->latest('id');
        if ($isStaff) {
            $allBookingsQuery->where('user_id', $user->id);
        } elseif ($isAffiliateAdmin) {
            if ($affiliateId) {
                $allBookingsQuery->where(function ($q) use ($affiliateId, $user) {
                    $q->where('affiliate_id', $affiliateId)->orWhere('user_id', $user->id);
                });
            } else {
                $allBookingsQuery->where('user_id', $user->id);
            }
        } elseif ($affiliateId) {
            $allBookingsQuery->where('affiliate_id', $affiliateId);
        }
        $allBookings = $allBookingsQuery->take(100)->get();
        $recentBookings = $allBookings->take(5);

        return response()->json([
            'status' => 'success',
            'date' => $todayDate,
            'metrics' => [
                // 4 Core Metrics from booking-page.blade.php
                'availableUnits' => $availableUnits,
                'unreturnedUnits' => $unreturnedUnits,
                'bookingToday' => $bookingToday,
                'revenueToday' => $revenueToday,
                'iphonesAvailable' => $availableUnits,
                'returnToday' => $unreturnedUnits,
                'todayBookings' => $bookingToday,
                'todayRevenue' => $revenueToday,

                // Legacy/General operational stats (Tersedia + Disewa + Terlambat = Total)
                'activeRentals' => $activeRentals,
                'todayPickups' => $todayPickups,
                'todayReturns' => $todayReturns,
                'overdueReturns' => $overdueReturns,
                'rentedUnits' => $rentedUnits,
                'maintenanceUnits' => $maintenanceUnits,
                'totalUnits' => $totalUnits,
                'utilizationRate' => $utilizationRate,
                'active_rentals' => $activeRentals,
                'today_pickups' => $todayPickups,
                'today_returns' => $todayReturns,
                'overdue_returns' => $overdueReturns,
                'available_units' => $availableUnits,
                'unreturned_units' => $unreturnedUnits,
                'booking_today' => $bookingToday,
                'revenue_today' => $revenueToday,
                'rented_units' => $rentedUnits,
                'maintenance_units' => $maintenanceUnits,
                'total_units' => $totalUnits,
                'utilization_rate' => $utilizationRate,
            ],
            'financials' => [
                'totalRevenue' => $revenueToday,
                'totalHeldDeposit' => $totalHeldDeposit,
                'totalRefundedDeposit' => $totalRefundedDeposit,
                'total_revenue' => $revenueToday,
                'total_held_deposit' => $totalHeldDeposit,
                'total_refunded_deposit' => $totalRefundedDeposit,
            ],
            'actionItems' => BookingResource::collection($actionItems),
            'action_items' => BookingResource::collection($actionItems),
            'recentBookings' => BookingResource::collection($recentBookings),
            'recent_bookings' => BookingResource::collection($recentBookings),
            'allBookings' => BookingResource::collection($allBookings),
            'all_bookings' => BookingResource::collection($allBookings),
        ]);
    }

    /**
     * Sales and financial report by period with payment method & model breakdowns.
     * GET /api/v1/dashboard/sales-report
     * GET /api/v1/reports/sales
     */
    public function salesReport(Request $request): JsonResponse
    {
        $data = $this->computeSalesReportData($request);
        return response()->json($data);
    }

    /**
     * Export sales report to CSV format.
     * GET /api/v1/reports/export/csv
     * GET /api/v1/dashboard/export/csv
     * GET /api/v1/reports/sales/export
     */
    public function exportCsv(Request $request): \Symfony\Component\HttpFoundation\Response
    {
        $reportData = $this->computeSalesReportData($request);
        $transactions = $reportData['transactions'] ?? [];

        $output = fopen('php://temp', 'r+');
        // UTF-8 BOM for Excel compatibility
        fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

        // Header
        fputcsv($output, [
            'No',
            'Kode Pembayaran',
            'Kode Booking',
            'Nama Pelanggan',
            'Unit iPhone',
            'Metode Pembayaran',
            'Nominal Bayar',
            'Deposit',
            'Status Deposit',
            'Tanggal Transaksi',
            'Tipe',
        ]);

        foreach ($transactions as $idx => $t) {
            fputcsv($output, [
                $idx + 1,
                $t['payment_code'],
                $t['booking_code'],
                $t['customer_name'],
                $t['iphone_name'],
                $t['payment_method'],
                (int) $t['paid_amount'],
                (int) $t['deposit_amount'],
                $t['deposit_status'],
                $t['transaction_date'],
                $t['type'],
            ]);
        }

        // Summary footer
        fputcsv($output, []);
        fputcsv($output, ['RINGKASAN LAPORAN']);
        fputcsv($output, ['Periode', $reportData['period']]);
        fputcsv($output, ['Rentang Tanggal', $reportData['start_date'] . ' s/d ' . $reportData['end_date']]);
        fputcsv($output, ['Total Omzet', (int) ($reportData['summary']['totalRevenue'] ?? 0)]);
        fputcsv($output, ['Total Transaksi', (int) ($reportData['summary']['transactionCount'] ?? 0)]);
        fputcsv($output, ['Total Deposit Ditahan', (int) ($reportData['summary']['totalDepositsHeld'] ?? 0)]);
        fputcsv($output, ['Total Deposit Dikembalikan', (int) ($reportData['summary']['totalDepositsRefunded'] ?? 0)]);

        rewind($output);
        $csvContent = stream_get_contents($output);
        fclose($output);

        $filename = 'laporan-penjualan-' . ($reportData['start_date'] ?? date('Y-m-d')) . '.csv';

        return response($csvContent, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ]);
    }

    /**
     * Export closing report to thermal ESC/POS 58mm (32 columns) format.
     * GET /api/v1/reports/export/closing
     * GET /api/v1/dashboard/export/closing
     * GET /api/v1/reports/closing
     */
    public function exportClosingEscPos(Request $request): \Symfony\Component\HttpFoundation\Response
    {
        $reportData = $this->computeSalesReportData($request);
        $escPosText = $this->generateClosingEscPos($reportData);

        if ($request->wantsJson() || $request->query('format') === 'json') {
            return response()->json([
                'status' => 'success',
                'format' => 'esc_pos_58mm_32col',
                'filename' => 'closing-report-' . ($reportData['start_date'] ?? date('Y-m-d')) . '.txt',
                'text' => $escPosText,
                'summary' => $reportData['summary'],
                'paymentMethodBreakdown' => $reportData['paymentMethodBreakdown'],
            ]);
        }

        $filename = 'closing-report-' . ($reportData['start_date'] ?? date('Y-m-d')) . '.txt';

        return response($escPosText, 200, [
            'Content-Type' => 'text/plain; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ]);
    }

    /**
     * Compute full sales report data array from request parameters.
     */
    private function computeSalesReportData(Request $request): array
    {
        $timezone = 'Asia/Jakarta';
        $now = Carbon::now($timezone);

        $period = $request->query('period', 'Hari Ini');
        $periodLower = strtolower(trim($period));

        $startDateParam = $request->query('start_date');
        $endDateParam = $request->query('end_date');

        // Determine date range in Asia/Jakarta
        if ($startDateParam && $endDateParam) {
            $startDate = Carbon::parse($startDateParam, $timezone)->startOfDay();
            $endDate = Carbon::parse($endDateParam, $timezone)->endOfDay();
        } elseif ($startDateParam) {
            $startDate = Carbon::parse($startDateParam, $timezone)->startOfDay();
            $endDate = $now->copy()->endOfDay();
        } else {
            switch ($periodLower) {
                case 'hari ini':
                case 'hari_ini':
                case 'today':
                    $startDate = $now->copy()->startOfDay();
                    $endDate = $now->copy()->endOfDay();
                    break;
                case 'kemarin':
                case 'yesterday':
                    $startDate = $now->copy()->subDay()->startOfDay();
                    $endDate = $now->copy()->subDay()->endOfDay();
                    break;
                case 'minggu ini':
                case 'minggu_ini':
                case 'this_week':
                    $startDate = $now->copy()->startOfWeek();
                    $endDate = $now->copy()->endOfWeek();
                    break;
                case 'bulan ini':
                case 'bulan_ini':
                case 'this_month':
                    $startDate = $now->copy()->startOfMonth();
                    $endDate = $now->copy()->endOfMonth();
                    break;
                case 'bulan lalu':
                case 'bulan_lalu':
                case 'last_month':
                    $startDate = $now->copy()->subMonth()->startOfMonth();
                    $endDate = $now->copy()->subMonth()->endOfMonth();
                    break;
                case 'semua':
                case 'all':
                    $startDate = Carbon::create(2020, 1, 1, 0, 0, 0, $timezone);
                    $endDate = $now->copy()->endOfDay();
                    break;
                default:
                    $startDate = $now->copy()->startOfDay();
                    $endDate = $now->copy()->endOfDay();
                    break;
            }
        }

        // Authenticated user check for affiliate and role scoping
        $user = $this->resolveUser($request);
        $isSuperAdmin = $user && method_exists($user, 'hasRole') && $user->hasRole('super-admin');
        $isAffiliateAdmin = $user && method_exists($user, 'hasRole') && $user->hasRole('affiliate-admin');
        $isAdmin = $user && method_exists($user, 'hasRole') && $user->hasRole('admin');
        $isStaff = $user && ! $isSuperAdmin && ! $isAdmin && ! $isAffiliateAdmin;

        $affiliateId = null;
        if ($isAffiliateAdmin) {
            $affiliateId = $user->affiliate_id;
        } elseif ($isAdmin && $user->affiliate_id) {
            $affiliateId = $user->affiliate_id;
        } else {
            $affiliateId = $request->query('affiliate_id');
        }

        // Base BookingPayments query
        $paymentsQuery = BookingPayment::whereBetween('booking_payments.paid_at', [$startDate, $endDate]);

        if ($isStaff) {
            // Staff hanya boleh melihat pembayaran dan omzet dari booking miliknya sendiri
            $paymentsQuery->whereHas('booking', function ($bq) use ($user) {
                $bq->where('user_id', $user->id);
            });
        } elseif ($isAffiliateAdmin) {
            if ($affiliateId) {
                $paymentsQuery->whereHas('booking', function ($bq) use ($affiliateId, $user) {
                    $bq->where('affiliate_id', $affiliateId)
                       ->orWhere('user_id', $user->id);
                });
            } else {
                $paymentsQuery->whereHas('booking', function ($bq) use ($user) {
                    $bq->where('user_id', $user->id);
                });
            }
        } elseif ($affiliateId) {
            $paymentsQuery->whereHas('booking', function ($bq) use ($affiliateId) {
                $bq->where('affiliate_id', $affiliateId);
            });
        }

        // Filter payment method
        $paymentMethod = $request->query('payment_method', $request->query('paymentMethod'));
        if ($paymentMethod && ! in_array(strtolower($paymentMethod), ['semua', 'all', 'semua metode'])) {
            $paymentsQuery->whereHas('payment', function ($pq) use ($paymentMethod) {
                $pq->where('name', 'like', "%{$paymentMethod}%")
                    ->orWhere('slug', 'like', "%{$paymentMethod}%");
            });
        }

        // Filter model iPhone
        $modelFilter = $request->query('model', $request->query('iphone_model'));
        if ($modelFilter && ! in_array(strtolower($modelFilter), ['semua', 'all'])) {
            $paymentsQuery->whereHas('booking.iphone', function ($iq) use ($modelFilter) {
                $iq->where('name', 'like', "%{$modelFilter}%");
            });
        }

        // 1. Direct database aggregation: Total Revenue and Total Transaction Count
        $summaryRow = (clone $paymentsQuery)
            ->selectRaw('COUNT(*) as total_count, COALESCE(SUM(booking_payments.amount), 0) as total_amount')
            ->first();
        $totalRevenue = (float) ($summaryRow->total_amount ?? 0);
        $transactionCount = (int) ($summaryRow->total_count ?? 0);
        $averageTransactionValue = $transactionCount > 0 ? round($totalRevenue / $transactionCount, 2) : 0.0;

        // 2. Direct database aggregation: Payment method breakdown via SQL GROUP BY
        $pmQuery = (clone $paymentsQuery)
            ->leftJoin('payments', 'booking_payments.payment_id', '=', 'payments.id')
            ->groupBy('payments.name')
            ->selectRaw('COALESCE(payments.name, "Tunai Kasir") as method_name, SUM(booking_payments.amount) as method_total')
            ->get();

        $paymentMethodBreakdown = [];
        $cashAmount = 0.0;
        $transferAmount = 0.0;
        $qrisAmount = 0.0;

        foreach ($pmQuery as $row) {
            $name = $row->method_name;
            $amt = (float) $row->method_total;
            $paymentMethodBreakdown[$name] = $amt;

            $methodLower = strtolower($name);
            if (str_contains($methodLower, 'cash') || str_contains($methodLower, 'tunai')) {
                $cashAmount += $amt;
            } elseif (str_contains($methodLower, 'transfer') || str_contains($methodLower, 'bank') || str_contains($methodLower, 'va')) {
                $transferAmount += $amt;
            } elseif (str_contains($methodLower, 'qris')) {
                $qrisAmount += $amt;
            } else {
                $cashAmount += $amt;
            }
        }

        // 3. Direct database aggregation: Type breakdown via SQL GROUP BY
        $typeRows = (clone $paymentsQuery)
            ->groupBy('booking_payments.type')
            ->selectRaw('COALESCE(booking_payments.type, "payment") as tx_type, SUM(booking_payments.amount) as type_total')
            ->get();

        $typeBreakdown = [
            'dp' => 0.0,
            'payment' => 0.0,
            'extend' => 0.0,
            'penalty' => 0.0,
        ];

        foreach ($typeRows as $row) {
            $typeName = strtolower($row->tx_type ?? 'payment');
            if (!array_key_exists($typeName, $typeBreakdown)) {
                $typeName = 'payment';
            }
            $typeBreakdown[$typeName] += (float) $row->type_total;
        }

        // 4. Direct database aggregation: Model rental count & model revenue via SQL GROUP BY
        $modelRows = (clone $paymentsQuery)
            ->join('bookings', 'booking_payments.booking_id', '=', 'bookings.id')
            ->join('iphones', 'bookings.iphone_id', '=', 'iphones.id')
            ->groupBy('iphones.name')
            ->selectRaw('iphones.name, COUNT(booking_payments.id) as count, SUM(booking_payments.amount) as revenue')
            ->orderByDesc('revenue')
            ->get();

        $modelRentalCount = [];
        $modelRevenue = [];

        foreach ($modelRows as $row) {
            $modelRentalCount[$row->name] = (int) $row->count;
            $modelRevenue[$row->name] = (float) $row->revenue;
        }

        // 5. Pagination for transaction list to avoid heavy payloads and mobile lag
        $isExport = $request->boolean('all_transactions') || $request->is('*/export/*') || str_contains($request->path(), 'export');
        $perPage = $isExport ? 1000 : min(100, max(1, (int) $request->query('per_page', 20)));
        $page = max(1, (int) $request->query('page', 1));

        $paginated = (clone $paymentsQuery)
            ->with(['booking.iphone', 'payment'])
            ->orderBy('booking_payments.paid_at', 'desc')
            ->paginate($perPage, ['*'], 'page', $page);

        $transactionList = [];
        foreach ($paginated->items() as $p) {
            $amt = (float) $p->amount;
            $methodName = $p->payment?->name ?? 'Tunai Kasir';
            $iphoneName = $p->booking?->iphone?->name ?? 'iPhone';

            $typeLabel = match (strtolower($p->type ?? 'payment')) {
                'dp' => 'DP',
                'extend' => 'Extend Sewa',
                'penalty' => 'Penalty/Denda',
                default => 'Pelunasan',
            };
            $jaminan = $p->booking?->jaminan_type ? "Jaminan {$p->booking->jaminan_type}" : 'KTP Terverifikasi';

            $transactionList[] = [
                'id' => $p->id,
                'booking_id' => $p->booking_id ?? 0,
                'payment_code' => 'PAY-' . ($p->booking?->booking_code ?? $p->id),
                'booking_code' => $p->booking?->booking_code ?? '-',
                'customer_name' => $p->booking?->customer_name ?? 'Pelanggan',
                'customer_phone' => $p->booking?->customer_phone ?? '-',
                'iphone_name' => $iphoneName,
                'payment_method' => $methodName,
                'paid_amount' => $amt,
                'rent_total' => (float) ($p->booking?->price ?? $amt),
                'remaining_amount' => max(0.0, (float) (($p->booking?->price ?? $amt) - $amt)),
                'deposit_amount' => 0.0,
                'deposit_status' => 'none',
                'payment_status' => $p->booking?->payment_status ?? 'paid',
                'transaction_date' => $p->paid_at?->toIso8601String() ?? $p->created_at?->toIso8601String(),
                'type' => $p->type ?? 'payment',
                'notes' => "{$typeLabel}|{$jaminan}",
            ];
        }

        // Deposits in period
        $totalDepositsHeld = 0.0;
        $totalDepositsRefunded = 0.0;

        // Affiliate revenue breakdown for Super-Admin & Admin (Staff cannot view other affiliates breakdown)
        $affiliateBreakdown = [];
        if (! $isAffiliateAdmin && ! $isStaff) {
            $affiliates = \App\Models\Affiliate::all();
            $today = Carbon::today('Asia/Jakarta');

            foreach ($affiliates as $aff) {
                $isPusat = str_contains(strtolower($aff->code . ' ' . $aff->name . ' ' . $aff->slug), 'pusat');
                if ($isPusat) {
                    $affRev = (float) \App\Models\BookingPayment::whereBetween('paid_at', [$startDate, $endDate])
                        ->where(function ($q) use ($aff) {
                            $q->whereNull('booking_id')
                              ->orWhereHas('booking', function ($b) use ($aff) {
                                  $b->where('affiliate_id', $aff->id)->orWhereNull('affiliate_id');
                              });
                        })->sum('amount');
                    $affTxCount = \App\Models\Booking::whereBetween('created_at', [$startDate, $endDate])
                        ->where(function ($b) use ($aff) {
                            $b->where('affiliate_id', $aff->id)->orWhereNull('affiliate_id');
                        })->count();
                } else {
                    $affRev = (float) \App\Models\BookingPayment::whereBetween('paid_at', [$startDate, $endDate])
                        ->whereHas('booking', function ($q) use ($aff) {
                            $q->where('affiliate_id', $aff->id);
                        })->sum('amount');
                    $affTxCount = \App\Models\Booking::whereBetween('created_at', [$startDate, $endDate])
                        ->where('affiliate_id', $aff->id)->count();
                }

                $affiliateBreakdown[] = [
                    'id' => $aff->id,
                    'code' => $aff->code,
                    'name' => $aff->name,
                    'slug' => $aff->slug,
                    'city' => $aff->city ?? '-',
                    'revenue' => $affRev,
                    'booking_count' => $affTxCount,
                    'is_active' => (bool)$aff->is_active,
                    'iphones_count' => (int)$aff->iphones()->count(),
                    'revenue_today' => (float) \App\Models\BookingPayment::whereDate('paid_at', $today)
                        ->whereHas('booking', function ($q) use ($aff) {
                            $q->where('affiliate_id', $aff->id);
                        })->sum('amount'),
                    'total_revenue' => (float) \App\Models\BookingPayment::whereHas('booking', function ($q) use ($aff) {
                        $q->where('affiliate_id', $aff->id);
                    })->sum('amount'),
                ];
            }
        }

        return [
            'status' => 'success',
            'period' => $period,
            'start_date' => $startDate->toDateString(),
            'end_date' => $endDate->toDateString(),
            'summary' => [
                'totalRevenue' => $totalRevenue,
                'totalDepositsHeld' => $totalDepositsHeld,
                'totalDepositsRefunded' => $totalDepositsRefunded,
                'transactionCount' => $transactionCount,
                'averageTransactionValue' => $averageTransactionValue,
                'cashAmount' => $cashAmount,
                'transferAmount' => $transferAmount,
                'qrisAmount' => $qrisAmount,
                'total_revenue' => $totalRevenue,
                'total_deposits_held' => $totalDepositsHeld,
                'total_deposits_refunded' => $totalDepositsRefunded,
                'transaction_count' => $transactionCount,
                'average_transaction_value' => $averageTransactionValue,
                'cash_amount' => $cashAmount,
                'transfer_amount' => $transferAmount,
                'qris_amount' => $qrisAmount,
            ],
            'paymentMethodBreakdown' => empty($paymentMethodBreakdown) ? (object)[] : $paymentMethodBreakdown,
            'payment_method_breakdown' => empty($paymentMethodBreakdown) ? (object)[] : $paymentMethodBreakdown,
            'typeBreakdown' => empty($typeBreakdown) ? (object)[] : $typeBreakdown,
            'type_breakdown' => empty($typeBreakdown) ? (object)[] : $typeBreakdown,
            'modelRentalCount' => empty($modelRentalCount) ? (object)[] : $modelRentalCount,
            'model_rental_count' => empty($modelRentalCount) ? (object)[] : $modelRentalCount,
            'modelRevenue' => empty($modelRevenue) ? (object)[] : $modelRevenue,
            'model_revenue' => empty($modelRevenue) ? (object)[] : $modelRevenue,
            'affiliateBreakdown' => $affiliateBreakdown,
            'affiliate_breakdown' => $affiliateBreakdown,
            'pagination' => [
                'current_page' => $paginated->currentPage(),
                'last_page' => $paginated->lastPage(),
                'per_page' => $paginated->perPage(),
                'total' => $paginated->total(),
            ],
            'transactions' => $transactionList,
        ];
    }

    /**
     * Generate 32-column ESC/POS plain text thermal printout.
     */
    private function generateClosingEscPos(array $reportData): string
    {
        $width = 32;
        $divider = str_repeat('=', $width);
        $subDivider = str_repeat('-', $width);

        $center = function (string $text) use ($width) {
            if (strlen($text) >= $width) {
                return substr($text, 0, $width);
            }
            $left = (int) floor(($width - strlen($text)) / 2);
            $right = $width - strlen($text) - $left;
            return str_repeat(' ', $left) . $text . str_repeat(' ', $right);
        };

        $twoCol = function (string $left, string $right) use ($width) {
            $totalLen = strlen($left) + strlen($right) + 1;
            if ($totalLen > $width) {
                $maxLeft = $width - strlen($right) - 1;
                if ($maxLeft > 0 && strlen($left) > $maxLeft) {
                    $left = substr($left, 0, $maxLeft);
                }
            }
            $spaces = max(1, $width - strlen($left) - strlen($right));
            return $left . str_repeat(' ', $spaces) . $right;
        };

        $summary = $reportData['summary'] ?? [];
        $breakdown = $reportData['paymentMethodBreakdown'] ?? [];
        $totalRev = number_format((float) ($summary['totalRevenue'] ?? 0), 0, ',', '.');
        $heldDep = number_format((float) ($summary['totalDepositsHeld'] ?? 0), 0, ',', '.');
        $refundDep = number_format((float) ($summary['totalDepositsRefunded'] ?? 0), 0, ',', '.');
        $txCount = $summary['transactionCount'] ?? 0;

        $lines = [];
        $lines[] = $divider;
        $lines[] = $center('SKYRENTAL OUTLET');
        $lines[] = $center('LAPORAN PENUTUPAN KASIR');
        $lines[] = $center('OUTLET MALIOBORO');
        $lines[] = $divider;

        $now = Carbon::now('Asia/Jakarta');
        $lines[] = $twoCol('Waktu Cetak :', $now->format('d/m/Y H:i'));
        $lines[] = $twoCol('Kasir/Admin :', 'Admin SKYRental');
        $lines[] = $twoCol('Periode     :', substr($reportData['period'] ?? 'Hari Ini', 0, 15));
        $lines[] = $subDivider;

        $lines[] = $center('RINGKASAN OMZET & KAS');
        $lines[] = $twoCol('Total Omzet :', 'Rp ' . $totalRev);
        $lines[] = $twoCol('Total Trans :', $txCount . ' Transaksi');
        $lines[] = $twoCol('Dep. Kasir  :', 'Rp ' . $heldDep);
        $lines[] = $twoCol('Dep. Refund :', 'Rp ' . $refundDep);
        $lines[] = $subDivider;

        $lines[] = $center('RINCIAN METODE BAYAR');
        foreach ($breakdown as $method => $amount) {
            $lines[] = $twoCol(substr($method, 0, 14), 'Rp ' . number_format((float) $amount, 0, ',', '.'));
        }
        $lines[] = $subDivider;

        $lines[] = $center('STATUS TUTUP KASIR: VALID');
        $lines[] = $center('DIARSIPKAN SECARA ELEKTRONIK');
        $lines[] = $divider;
        $lines[] = '';
        $lines[] = '';

        return implode("\n", $lines);
    }
}
