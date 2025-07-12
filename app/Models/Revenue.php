<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Revenue extends Model
{
    //
    protected $fillable = [
        'booking_id',
        'amount',
    ];

    public function booking()
    {
        return $this->belongsTo(Booking::class);
    }
}
