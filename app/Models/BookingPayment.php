<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BookingPayment extends Model
{
    protected $fillable = [
        'booking_id',
        'affiliate_id',
        'payment_id',
        'amount',
        'type',
        'paid_at',
        'user_id',
        'note',
        'pay',
        'change',
        'is_void',
        'void_reason',
        'voided_by',
        'voided_at',
    ];

    protected $casts = [
        'paid_at' => 'datetime',
        'is_void' => 'boolean',
        'voided_at' => 'datetime',
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
            if (empty($payment->affiliate_id) && $payment->booking_id) {
                $payment->affiliate_id = $payment->booking?->affiliate_id;
            }
        });
    }

    public function booking()
    {
        return $this->belongsTo(Booking::class);
    }

    public function affiliate()
    {
        return $this->belongsTo(Affiliate::class);
    }

    public function payment()
    {
        return $this->belongsTo(Payment::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function voidedBy()
    {
        return $this->belongsTo(User::class, 'voided_by');
    }

    public function scopeActive($query)
    {
        return $query->where('is_void', false);
    }

    public function getCreatedAttribute()
    {
        return $this->paid_at ?? $this->created_at;
    }
}
