<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Affiliate extends Model
{
    /** @use HasFactory<\Database\Factories\AffiliateFactory> */
    use HasFactory;

    protected $fillable = [
        'code',
        'name',
        'slug',
        'email',
        'phone',
        'address',
        'city',
        'province',
        'postal_code',
        'latitude',
        'longitude',
        'logo',
        'description',
        'is_active',
    ];

    public function users()
    {
        return $this->hasMany(User::class);
    }

    public function iphones()
    {
        return $this->hasMany(Iphones::class);
    }

    public function bookings()
    {
        return $this->hasMany(Booking::class);
    }

    public function transferOut()
    {
        return $this->hasMany(IphoneTransfer::class, 'from_affiliate_id');
    }

    public function transferIn()
    {
        return $this->hasMany(IphoneTransfer::class, 'to_affiliate_id');
    }
    // public function maintenances()
    // {
    //     return $this->hasMany(Maintenance::class);
    // }
}
