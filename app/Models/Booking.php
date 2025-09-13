<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Booking extends Model
{
    /** @use HasFactory<\Database\Factories\BookingFactory> */
    use HasFactory;

    protected $fillable = [
        'iphone_id',
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
        'reminder_sent'
    ];

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
}
