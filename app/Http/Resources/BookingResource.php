<?php

namespace App\Http\Resources;

use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BookingResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $deposit = 0.0;

        // Calculate late / overdue and due today indicators
        $isLate = false;
        $isOverdue = false;
        $isDueToday = false;
        $overdueMinutes = 0;
        $diffInHours = 0.0;
        $hoursLate = 0;
        $lateMinutes = 0;
        $daysLate = 0;
        $estimatedLateFee = 0.0;
        $now = Carbon::now('Asia/Jakarta');

        if ($this->end_booking_date) {
            $endTimeStr = trim((string) ($this->end_time ?: '23:59:59'));
            if (str_contains($endTimeStr, ' ')) {
                $parts = explode(' ', $endTimeStr);
                $endTimeStr = end($parts);
            }
            $endDateTime = Carbon::parse("{$this->end_booking_date} {$endTimeStr}", 'Asia/Jakarta');
            $isDueToday = $endDateTime->isSameDay($now);

            $diffInMinutes = $endDateTime->diffInMinutes($now, false);

            if (in_array($this->status, ['confirmed', 'rented', 'disewa']) && $diffInMinutes > 0) {
                $isOverdue = true;
                $isLate = $diffInMinutes > 90; // DetailBooking.php standard: > 90 minutes is late
                $overdueMinutes = (int) $diffInMinutes;
                $diffInHours = max(0.0, $diffInMinutes / 60);
                $hoursLate = max(0, (int) floor($diffInHours));
                $lateMinutes = max(0, (int) round(($diffInHours - $hoursLate) * 60));
                $daysLate = (int) floor($hoursLate / 24);

                $lateHoursForPenalty = $hoursLate;
                if ($lateHoursForPenalty > 1) { // 1 hour tolerance
                    $lateHoursForPenalty -= 1;
                    $durations = ($this->relationLoaded('iphone') && $this->iphone)
                        ? $this->iphone->durations()->orderByDesc('hours')->get()
                        : collect([]);
                    $remainingHours = $lateHoursForPenalty;
                    foreach ($durations as $package) {
                        if ($remainingHours < $package->hours) {
                            continue;
                        }
                        $count = intdiv($remainingHours, $package->hours);
                        $estimatedLateFee += $count * $package->pivot->price;
                        $remainingHours -= $count * $package->hours;
                        if ($remainingHours <= 0) {
                            break;
                        }
                    }
                    if ($remainingHours > 0) {
                        $estimatedLateFee += $remainingHours * 5000;
                    }
                }
            }
        }

        if ($this->status === 'returned') {
            $latestRet = $this->relationLoaded('latestReturn') ? $this->latestReturn : ($this->relationLoaded('returns') ? $this->returns->last() : null);
            if ($latestRet && (float) $latestRet->penalty_fee > 0) {
                $estimatedLateFee = (float) $latestRet->penalty_fee;
            }
        }

        $totalPaid = (float) ($this->relationLoaded('paymentTransactions') ? $this->paymentTransactions->whereIn('type', ['dp', 'payment', 'pelunasan'])->sum('amount') : ($this->payment_status === 'paid' ? $this->price : 0));
        $remainingPayment = max(0, (float) $this->price - $totalPaid);

        return [
            'id' => $this->id,
            'booking_code' => $this->booking_code,
            'customer' => [
                'name' => $this->customer_name,
                'phone' => $this->customer_phone,
                'email' => $this->customer_email,
                'address' => $this->address,
                'jaminan_type' => $this->jaminan_type ?? 'KTP',
            ],
            'customer_name' => $this->customer_name,
            'customer_phone' => $this->customer_phone,
            'customer_email' => $this->customer_email,
            'address' => $this->address,
            'pickup_type' => $this->pickup_type ?? 'Outlet',
            'jaminan_type' => $this->jaminan_type ?? 'KTP',
            'start_booking_date' => $this->start_booking_date,
            'start_time' => $this->start_time,
            'end_booking_date' => $this->end_booking_date,
            'end_time' => $this->end_time,
            'duration' => (int) $this->duration,
            'price' => (float) $this->price,
            'deposit' => 0.0,
            'total_bill' => (float) $this->price,
            'total_paid' => $totalPaid,
            'remaining_payment' => $remainingPayment,
            'status' => $this->status,
            'payment_status' => $this->payment_status ?? 'unpaid',
            'picked_up_at' => null,
            'waktu_pengambilan' => null,
            'picked_up_by' => null,
            'reminder_sent' => (bool) $this->reminder_sent,
            'is_late' => $isLate,
            'is_overdue' => $isOverdue,
            'is_due_today' => $isDueToday,
            'diff_hours' => round($diffInHours, 2),
            'hours_late' => $hoursLate,
            'late_hours' => $hoursLate,
            'late_minutes' => $lateMinutes,
            'duration_late_text' => ($hoursLate == 0 && $lateMinutes == 0) ? '0 menit' : ($hoursLate > 0 ? "{$hoursLate} jam {$lateMinutes} menit" : "{$lateMinutes} menit"),
            'days_late' => $daysLate,
            'overdue_minutes' => $overdueMinutes,
            'estimated_late_fee' => (float) $estimatedLateFee,
            'late_fee' => (float) $estimatedLateFee,
            'actions' => [
                'can_pickup' => $this->status === 'confirmed',
                'can_return' => in_array($this->status, ['confirmed', 'rented']),
                'can_cancel' => in_array($this->status, ['pending', 'confirmed']),
                'can_extend' => $this->status === 'rented',
            ],
            'can_pickup' => $this->status === 'confirmed',
            'can_return' => in_array($this->status, ['confirmed', 'rented']),
            'verification' => [
                'can_pickup' => $this->status === 'confirmed',
                'pickup_status' => match ($this->status) {
                    'confirmed' => 'ready_for_pickup',
                    'rented', 'disewa' => 'already_picked_up',
                    'returned' => 'already_returned',
                    'cancelled' => 'cancelled',
                    default => 'waiting_confirmation',
                },
                'collateral_type' => $this->jaminan_type ?? 'KTP',
                'payment_ready' => $this->payment_status === 'paid',
                'is_verified' => $this->status === 'confirmed',
            ],
            'iphone' => $this->whenLoaded('iphone', function () {
                return [
                    'id' => $this->iphone?->id,
                    'name' => $this->iphone?->name,
                    'storage' => $this->iphone?->storage ?? '128GB',
                    'color' => $this->iphone?->color ?? 'Default',
                    'serial_number' => $this->iphone?->serial_number,
                    'asset_code' => $this->iphone?->asset_code,
                    'status' => $this->iphone?->status ?? 'ready',
                    'battery_health' => $this->iphone?->battery_health ?? 100,
                    'physical_condition' => $this->iphone?->physical_condition ?? 'Mulus (Sempurna)',
                    'photo_url' => ($this->iphone?->gallery?->image ?? $this->iphone?->gallery?->photo)
                        ? asset('storage/' . ($this->iphone?->gallery?->image ?? $this->iphone?->gallery?->photo))
                        : null,
                    'durations' => ($this->iphone && $this->iphone->relationLoaded('durations'))
                        ? $this->iphone->durations->map(function ($d) {
                            return [
                                'id' => $d->id,
                                'name' => $d->name,
                                'hours' => (int) $d->hours,
                                'price' => (float) ($d->pivot->price ?? 0),
                            ];
                        })->values()->all()
                        : (($this->iphone) ? $this->iphone->durations()->get()->map(function ($d) {
                            return [
                                'id' => $d->id,
                                'name' => $d->name,
                                'hours' => (int) $d->hours,
                                'price' => (float) ($d->pivot->price ?? 0),
                            ];
                        })->values()->all() : []),
                ];
            }),
            'payment' => $this->whenLoaded('payment', function () {
                return $this->payment ? [
                    'id' => $this->payment->id,
                    'name' => $this->payment->name,
                ] : null;
            }),
            'payments_history' => $this->whenLoaded('bookingPayments', function () {
                return $this->bookingPayments;
            }),
            'returns' => $this->whenLoaded('returns', function () {
                return $this->returns;
            }),
            'latest_return' => $this->whenLoaded('latestReturn', function () {
                return $this->latestReturn;
            }),
            'revenue' => $this->whenLoaded('revenue', function () {
                return $this->revenue;
            }),
            'user' => $this->whenLoaded('user', function () {
                return $this->user ? [
                    'id' => $this->user->id,
                    'name' => $this->user->name,
                    'email' => $this->user->email,
                ] : null;
            }),
            'user_id' => $this->user_id,
            'affiliate_id' => $this->affiliate_id,
            'user_name' => $this->user?->name,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}