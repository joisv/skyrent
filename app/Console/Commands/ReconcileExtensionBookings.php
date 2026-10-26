<?php

namespace App\Console\Commands;

use App\Models\Booking;
use App\Models\BookingPayment;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ReconcileExtensionBookings extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'finance:reconcile-extension-bookings
                            {--dry-run : Simulate reconciliation without saving changes}
                            {--apply : Execute and persist the reconciliation updates}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Reconcile historical bookings where contract price was inflated and payment status degraded by rental extensions';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $apply = $this->option('apply');
        $isDryRun = !$apply;

        $this->info("==================================================================");
        $this->info($isDryRun ? " Running Extension Reconciliation [DRY RUN MODE]" : " Executing Extension Reconciliation [APPLY MODE]");
        $this->info("==================================================================");

        // Find all bookings with at least one 'extend' payment
        $extendBookings = Booking::whereHas('paymentTransactions', function ($q) {
            $q->where('type', 'extend');
        })->with('paymentTransactions')->get();

        $candidates = [];

        foreach ($extendBookings as $booking) {
            $payments = $booking->paymentTransactions;

            $baseRentalPaid = $payments->whereIn('type', ['dp', 'payment', 'pelunasan'])->sum('amount');
            $extendPaid = $payments->where('type', 'extend')->sum('amount');

            // If booking price was inflated by exactly the extension amount
            // and base rental was fully paid, but status became 'partial'
            if ($extendPaid > 0 && $baseRentalPaid > 0) {
                $originalBasePrice = $booking->price - $extendPaid;

                if (abs($originalBasePrice - $baseRentalPaid) < 1.0 && $booking->payment_status === 'partial') {
                    $candidates[] = [
                        'booking' => $booking,
                        'current_price' => $booking->price,
                        'corrected_price' => $originalBasePrice,
                        'current_status' => $booking->payment_status,
                        'corrected_status' => 'paid',
                        'base_paid' => $baseRentalPaid,
                        'extend_paid' => $extendPaid,
                    ];
                }
            }
        }

        $totalCount = count($candidates);
        $this->info("Found {$totalCount} candidate booking(s) for reconciliation.");

        if ($totalCount === 0) {
            $this->info("No corrupted booking records found. Database is clean.");
            return 0;
        }

        $tableRows = [];
        foreach ($candidates as $c) {
            $tableRows[] = [
                'ID' => $c['booking']->id,
                'Code' => $c['booking']->booking_code ?? 'N/A',
                'Cur Price' => number_format($c['current_price'], 0, ',', '.'),
                'New Price' => number_format($c['corrected_price'], 0, ',', '.'),
                'Cur Status' => $c['current_status'],
                'New Status' => $c['corrected_status'],
                'Base Paid' => number_format($c['base_paid'], 0, ',', '.'),
                'Extend Paid' => number_format($c['extend_paid'], 0, ',', '.'),
            ];
        }

        $this->table(['ID', 'Code', 'Cur Price', 'New Price', 'Cur Status', 'New Status', 'Base Paid', 'Extend Paid'], $tableRows);

        if ($isDryRun) {
            $this->warn("DRY RUN completed. To persist changes, re-run with: --apply");
            return 0;
        }

        DB::beginTransaction();
        try {
            foreach ($candidates as $c) {
                /** @var Booking $booking */
                $booking = $c['booking'];
                $booking->price = $c['corrected_price'];
                $booking->payment_status = $c['corrected_status'];
                $booking->save();
            }
            DB::commit();
            $this->info("Successfully reconciled {$totalCount} booking(s).");
        } catch (\Throwable $e) {
            DB::rollBack();
            $this->error("Failed to reconcile bookings: " . $e->getMessage());
            return 1;
        }

        return 0;
    }
}
