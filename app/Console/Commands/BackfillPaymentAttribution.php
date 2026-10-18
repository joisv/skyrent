<?php

namespace App\Console\Commands;

use App\Models\Affiliate;
use App\Models\BookingPayment;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class BackfillPaymentAttribution extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'finance:backfill-payment-attribution 
                            {--dry-run : Simulate the backfill without saving changes}
                            {--apply : Execute and persist the backfill updates}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Backfill affiliate_id on historical booking_payments using booking attribution and cashier counter evidence (Decision 2)';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $apply = $this->option('apply');
        $isDryRun = !$apply;

        $this->info("==================================================================");
        $this->info($isDryRun ? " Running Backfill Payment Attribution [DRY RUN MODE]" : " Executing Backfill Payment Attribution [APPLY MODE]");
        $this->info("==================================================================");

        $unassigned = BookingPayment::whereNull('affiliate_id')
            ->with(['booking.iphone', 'user'])
            ->get();

        $totalCount = $unassigned->count();
        $this->info("Found {$totalCount} booking_payments with NULL affiliate_id.");

        if ($totalCount === 0) {
            $this->info("All booking payments already have affiliate_id populated. Nothing to do.");
            return 0;
        }

        $affiliates = Affiliate::pluck('name', 'id')->toArray();
        $gentengPusat = Affiliate::where('slug', 'genteng-pusat')
            ->orWhere('name', 'like', '%Pusat%')
            ->orWhere('id', 4)
            ->first();
        $gentengPusatId = $gentengPusat?->id ?? 4; // Genteng Pusat

        $fromBooking = 0;
        $fromCashier = 0;
        $unresolved = 0;
        $updates = [];
        $affiliateTotals = [];

        foreach ($unassigned as $payment) {
            $targetAffiliateId = null;
            $resolutionMethod = '';

            if ($payment->booking && !empty($payment->booking->affiliate_id)) {
                $targetAffiliateId = (int) $payment->booking->affiliate_id;
                $resolutionMethod = 'booking';
                $fromBooking++;
            } elseif ($payment->user_id) {
                // Cashier counter attribution (Decision 2)
                // Admin (ID 1), Skyrental Pusat (ID 8) or users with affiliate_id = 4
                $targetAffiliateId = $gentengPusatId;
                $resolutionMethod = 'cashier_genteng_pusat';
                $fromCashier++;
            } else {
                // Fallback to Genteng Pusat if user is missing
                $targetAffiliateId = $gentengPusatId;
                $resolutionMethod = 'cashier_genteng_pusat';
                $fromCashier++;
            }

            if ($targetAffiliateId) {
                $updates[] = [
                    'id' => $payment->id,
                    'affiliate_id' => $targetAffiliateId,
                    'amount' => (float) $payment->amount,
                    'method' => $resolutionMethod,
                ];

                $affName = $affiliates[$targetAffiliateId] ?? "Affiliate #{$targetAffiliateId}";
                if (!isset($affiliateTotals[$affName])) {
                    $affiliateTotals[$affName] = ['count' => 0, 'amount' => 0.0];
                }
                $affiliateTotals[$affName]['count']++;
                $affiliateTotals[$affName]['amount'] += (float) $payment->amount;
            } else {
                $unresolved++;
            }
        }

        $this->line("");
        $this->info("Attribution Plan Summary:");
        $this->table(
            ['Method', 'Count'],
            [
                ['From Booking affiliate_id', $fromBooking],
                ['From Cashier Location (Genteng Pusat ID 4)', $fromCashier],
                ['Unresolved / Remaining NULL', $unresolved],
                ['Total Target Records', count($updates)],
            ]
        );

        $this->line("");
        $this->info("Target Attribution by Branch:");
        $branchRows = [];
        foreach ($affiliateTotals as $name => $stat) {
            $branchRows[] = [
                $name,
                $stat['count'],
                'Rp ' . number_format($stat['amount'], 0, ',', '.'),
            ];
        }
        $this->table(['Branch (Affiliate)', 'Payment Count', 'Total Cash Volume'], $branchRows);

        if ($isDryRun) {
            $this->warn("\n[DRY RUN] No database changes were applied. Use --apply to execute the updates.");
            return 0;
        }

        $this->line("");
        $this->info("Applying updates within database transaction...");

        DB::transaction(function () use ($updates) {
            foreach ($updates as $row) {
                DB::table('booking_payments')
                    ->where('id', $row['id'])
                    ->update(['affiliate_id' => $row['affiliate_id']]);
            }
        });

        $remainingNulls = DB::table('booking_payments')->whereNull('affiliate_id')->count();
        $this->info("SUCCESS: Backfilled {$totalCount} payment records.");
        $this->info("Remaining NULL affiliate_id payments: {$remainingNulls}");

        return 0;
    }
}
