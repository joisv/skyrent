<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Booking extends Model
{
    /** @use HasFactory<\Database\Factories\BookingFactory> */
    use HasFactory;

     protected $fillable = [
        'iphone_id',
        'customer_name',
        'customer_phone',
        'customer_email',
        'start_time',
        'end_time',
        'duration',
        'status',
    ];

    public function iphone()
    {
        return $this->belongsTo(Iphones::class);
    }
}
