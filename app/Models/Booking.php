<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Booking extends Model
{
    /** @use HasFactory<\Database\Factories\BookingFactory> */
    use HasFactory;

    protected $fillable = [
        'iphone_id',
        'customer_id',
        'customer_name',
        'customer_phone',
        'customer_email',
        'requested_booking_date',
        'requested_time',
        'start_booking_date',
        'start_time',
        'end_booking_date',
        'end_time',
        'duration',
        'status',
        'price',
        'created',
        'booking_code',
        'payment_id',
        'reminder_sent',
        'address',
        'pickup_type',
        'jaminan_type',
        'kia',
        'user_id',
        'affiliate_id',
        'payment_status',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public static function generateBookingCode()
    {
        do {
            // Format: SKY + TahunBulanTanggal + Random 4 digit
            $code = 'SKY' . now()->format('ymd') . Str::upper(Str::random(4));
        } while (self::where('booking_code', $code)->exists());

        return $code;
    }

    public function revenue()
    {
        return $this->hasOne(Revenue::class);
    }


    public function iphone()
    {
        return $this->belongsTo(Iphones::class);
    }

    public function payment()
    {
        return $this->belongsTo(Payment::class);
    }

    public function returns()
    {
        return $this->hasMany(ReturnIphone::class);
    }

    public function latestReturn()
    {
        return $this->hasOne(ReturnIphone::class, 'booking_id')->latestOfMany();
    }

    public function paymentProofs()
    {
        return $this->hasMany(PaymentProof::class);
    }

    public function bookingPayments()
    {
        return $this->hasMany(BookingPayment::class);
    }


    public function extendHours(int $hours): void
    {
        $start = Carbon::parse(
            "{$this->start_booking_date} {$this->start_time}",
            'Asia/Jakarta'
        );

        $end = Carbon::parse(
            "{$this->end_booking_date} {$this->end_time}",
            'Asia/Jakarta'
        );

        // Tambah jam
        $newEnd = $end->copy()->addHours($hours);

        $this->duration += $hours;
        $this->end_booking_date = $newEnd->toDateString();
        $this->end_time = $newEnd->format('H:i');

        $this->saveQuietly();
    }

    public function canExtend(int $hours): bool
    {
        $currentEnd = Carbon::parse(
            "{$this->end_booking_date} {$this->end_time}",
            'Asia/Jakarta'
        );

        $newEnd = $currentEnd->copy()->addHours($hours);

        return !Booking::where('iphone_id', $this->iphone_id)
            ->whereIn('status', ['pending', 'confirmed'])
            ->where('id', '!=', $this->id)
            ->get()
            ->contains(function ($booking) use ($currentEnd, $newEnd) {
                $start = Carbon::parse(
                    "{$booking->start_booking_date} {$booking->start_time}",
                    'Asia/Jakarta'
                );

                $end = Carbon::parse(
                    "{$booking->end_booking_date} {$booking->end_time}",
                    'Asia/Jakarta'
                );

                return $currentEnd->lt($end) && $newEnd->gt($start);
            });
    }

    public function affiliate()
    {
        return $this->belongsTo(Affiliate::class);
    }

    public function paymentTransactions()
    {
        return $this->hasMany(BookingPayment::class);
    }

    // public function bookingPayments()
    // {
    //     return $this->hasMany(BookingPayment::class);
    // }

    public function getTotalPaidAttribute(): float
    {
        if ($this->relationLoaded('paymentTransactions')) {
            return (float) $this->paymentTransactions->whereIn('type', ['dp', 'payment', 'pelunasan'])->sum('amount');
        }
        return (float) $this->paymentTransactions()->whereIn('type', ['dp', 'payment', 'pelunasan'])->sum('amount');
    }

    public function getRemainingPaymentAttribute(): float
    {
        return max(0.0, (float) $this->price - (float) $this->total_paid);
    }
    public function updatePaymentStatus(): void
    {
        $this->unsetRelation('paymentTransactions');
        $totalPaid = $this->total_paid;

        if ($totalPaid <= 0) {
            $status = 'unpaid';
        } elseif ($totalPaid < $this->price) {
            $status = 'partial';
        } else {
            $status = 'paid';
        }

        $this->update([
            'payment_status' => $status,
        ]);
    }

    public function getLateInfoAttribute(): array
    {
        $isLate = false;
        $isOverdue = false;
        $isDueToday = false;
        $diffHours = 0.0;
        $lateHours = 0;
        $lateMinutes = 0;
        $penalty = 0.0;
        $now = Carbon::now('Asia/Jakarta');

        if ($this->end_booking_date) {
            $endTime = $this->end_time ?: '23:59:59';
            $endDateTime = Carbon::parse("{$this->end_booking_date} {$endTime}", 'Asia/Jakarta');
            $isDueToday = $endDateTime->isSameDay($now);
            $diffInMinutes = $endDateTime->diffInMinutes($now, false);

            if (in_array($this->status, ['confirmed', 'rented', 'disewa']) && $diffInMinutes > 0) {
                $isOverdue = true;
                $isLate = $diffInMinutes > 90;
                $diffHours = max(0.0, $diffInMinutes / 60);
                $lateHours = max(0, (int) floor($diffHours));
                $lateMinutes = max(0, (int) round(($diffHours - $lateHours) * 60));

                $lateHoursForPenalty = $lateHours;
                if ($lateHoursForPenalty > 1) {
                    $lateHoursForPenalty -= 1;
                    $durations = ($this->relationLoaded('iphone') && $this->iphone)
                        ? $this->iphone->durations()->orderByDesc('hours')->get()
                        : collect([]);
                    $rem = $lateHoursForPenalty;
                    foreach ($durations as $pkg) {
                        if ($rem < $pkg->hours) continue;
                        $cnt = intdiv($rem, $pkg->hours);
                        $penalty += $cnt * ($pkg->pivot->price ?? 0);
                        $rem -= $cnt * $pkg->hours;
                        if ($rem <= 0) break;
                    }
                    if ($rem > 0) {
                        $penalty += $rem * 5000;
                    }
                }
            }
        }

        if ($this->status === 'returned') {
            $latestRet = $this->relationLoaded('latestReturn') ? $this->latestReturn : $this->latestReturn()->first();
            if ($latestRet && (float) $latestRet->penalty_fee > 0) {
                $penalty = (float) $latestRet->penalty_fee;
            }
        }

        $durationText = ($lateHours == 0 && $lateMinutes == 0)
            ? '0 menit'
            : ($lateHours > 0 ? "{$lateHours} jam {$lateMinutes} menit" : "{$lateMinutes} menit");

        return [
            'is_late' => $isLate,
            'is_overdue' => $isOverdue,
            'is_due_today' => $isDueToday,
            'diff_hours' => round($diffHours, 2),
            'hours' => $lateHours,
            'minutes' => $lateMinutes,
            'duration_text' => $durationText,
            'penalty' => (float) $penalty,
        ];
    }
}
