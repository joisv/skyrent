<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BookingPayment extends Model
{
    protected $fillable = [
        'booking_id',
        'payment_id',
        'amount',
        'type',
        'paid_at',
        'user_id',
        'note',
        'pay',
        'change'
    ];

    protected $casts = [
        'paid_at' => 'datetime',
    ];

    protected static function booted()
    {
        static::creating(function ($payment) {
            if ($payment->pay === null) {
                $payment->pay = $payment->amount ?? 0;
            }
            if ($payment->change === null) {
                $payment->change = 0;
            }
            if ($payment->type === 'rental') {
                $payment->type = 'payment';
            }
        });
    }

    public function booking()
    {
        return $this->belongsTo(Booking::class);
    }

    public function payment()
    {
        return $this->belongsTo(Payment::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
