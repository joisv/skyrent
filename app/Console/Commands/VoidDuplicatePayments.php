<?php

namespace App\Console\Commands;

use App\Models\Booking;
use App\Models\BookingPayment;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class VoidDuplicatePayments extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'finance:void-duplicate-payments
                            {--dry-run : Simulate voiding duplicate payments without persisting}
                            {--apply : Execute and persist the void operation}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Audit and void verified rapid burst duplicate payment transactions (Decision 1)';

    /**
     * The verified candidate pairs/clusters:
     * primary_id => [duplicate_ids...]
     */
    protected array $candidateClusters = [
        392 => [393],                       // Booking #3211 (SKY260808HZAV) - Rp 40.000 (8s apart)
        623 => [624, 625, 626, 627],        // Booking #3443 (SKY260823WERV) - 4x Rp 40.000 (0s apart)
        715 => [716],                       // Booking #3497 (SKY260826MNHB) - Rp 80.000 (0s apart)
        828 => [829],                       // Booking #3560 (SKY260830N8VP) - Rp 90.000 (0s apart)
    ];

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $apply = $this->option('apply');
        $isDryRun = !$apply;

        $this->info("==================================================================");
        $this->info($isDryRun ? " Auditing Duplicate Payments [DRY RUN MODE]" : " Executing Void Operation [APPLY MODE]");
        $this->info("==================================================================");

        $tableRows = [];
        $totalVoidAmount = 0.0;
        $targetsToVoid = [];
        $affectedBookingIds = [];

        foreach ($this->candidateClusters as $primaryId => $duplicateIds) {
            $primary = BookingPayment::find($primaryId);
            if (!$primary) {
                $this->warn("Primary payment ID {$primaryId} not found!");
                continue;
            }

            foreach ($duplicateIds as $dupId) {
                $dup = BookingPayment::find($dupId);
                if (!$dup) {
                    $this->warn("Duplicate payment ID {$dupId} not found!");
                    continue;
                }

                $diffSeconds = abs($primary->created_at->diffInSeconds($dup->created_at));
                $isSameBooking = ($primary->booking_id === $dup->booking_id);
                $isSameAmount = ((float)$primary->amount === (float)$dup->amount);
                $isAlreadyVoid = (bool) $dup->is_void;

                $booking = Booking::find($dup->booking_id);
                $bookingCode = $booking ? $booking->booking_code : "Booking #{$dup->booking_id}";

                $tableRows[] = [
                    'Duplicate ID' => $dup->id,
                    'Primary ID' => $primary->id,
                    'Booking Code' => $bookingCode,
                    'Amount' => 'Rp ' . number_format($dup->amount, 0, ',', '.'),
                    'Time Delta' => "{$diffSeconds}s",
                    'Status' => $isAlreadyVoid ? 'ALREADY VOID' : 'PENDING VOID',
                ];

                if (!$isAlreadyVoid && $isSameBooking && $isSameAmount) {
                    $targetsToVoid[] = [
                        'payment' => $dup,
                        'booking' => $booking,
                        'amount' => (float) $dup->amount,
                    ];
                    $totalVoidAmount += (float) $dup->amount;
                    $affectedBookingIds[$booking->id] = $booking;
                }
            }
        }

        $this->table(
            ['Duplicate ID', 'Primary ID', 'Booking Code', 'Amount', 'Time Delta', 'Status'],
            $tableRows
        );

        $this->info("Total candidate rows: " . count($tableRows));
        $this->info("Total rows to void: " . count($targetsToVoid));
        $this->info("Total phantom amount to void: Rp " . number_format($totalVoidAmount, 0, ',', '.'));

        if (count($targetsToVoid) === 0) {
            $this->info("No active duplicate payments to void. All candidate records are already void or invalid.");
            return 0;
        }

        if ($isDryRun) {
            $this->warn("\n[DRY RUN] No records were modified. Use --apply to execute the voiding.");
            return 0;
        }

        // Apply mode:
        $adminUser = User::where('email', 'like', '%admin%')->first() ?? User::first();
        $adminId = $adminUser?->id;
        $now = Carbon::now();

        $this->line("");
        $this->info("Applying soft-void with audit trail (voided_by: {$adminUser?->name})...");

        DB::transaction(function () use ($targetsToVoid, $adminId, $now) {
            foreach ($targetsToVoid as $target) {
                /** @var BookingPayment $p */
                $p = $target['payment'];
                $p->update([
                    'is_void' => true,
                    'void_reason' => 'rapid_duplicate_burst',
                    'voided_by' => $adminId,
                    'voided_at' => $now,
                ]);
            }
        });

        // Recalculate booking payment status for affected bookings
        $this->line("");
        $this->info("Recalculating booking payment status for affected bookings...");
        $bookingStatusRows = [];

        foreach ($affectedBookingIds as $booking) {
            $booking->refresh();
            $oldStatus = $booking->payment_status;
            $oldPaid = $booking->total_paid;
            $booking->updatePaymentStatus();
            $booking->refresh();

            $bookingStatusRows[] = [
                'Booking Code' => $booking->booking_code,
                'Total Price' => 'Rp ' . number_format($booking->price, 0, ',', '.'),
                'Adjusted Paid' => 'Rp ' . number_format($booking->total_paid, 0, ',', '.'),
                'Remaining' => 'Rp ' . number_format($booking->remaining_payment, 0, ',', '.'),
                'Payment Status' => $booking->payment_status,
            ];
        }

        $this->table(
            ['Booking Code', 'Total Price', 'Adjusted Paid', 'Remaining', 'Payment Status'],
            $bookingStatusRows
        );

        $this->info("SUCCESS: Voided " . count($targetsToVoid) . " duplicate payment rows totaling Rp " . number_format($totalVoidAmount, 0, ',', '.'));
        return 0;
    }
}
