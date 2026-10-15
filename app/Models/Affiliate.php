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

    protected $casts = [
        'is_active' => 'boolean',
        'latitude' => 'float',
        'longitude' => 'float',
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

    /**
     * Menentukan apakah cabang ini adalah cabang pusat (Central Store).
     */
    public function isPusat(): bool
    {
        $hasSuperAdmin = $this->relationLoaded('users')
            ? $this->users->contains(function ($u) {
                return $u->relationLoaded('roles')
                    ? $u->roles->contains('name', 'super-admin')
                    : (method_exists($u, 'hasRole') && $u->hasRole('super-admin'));
            })
            : $this->users()->whereHas('roles', fn($r) => $r->where('name', 'super-admin'))->exists();

        $nameCheck = \Illuminate\Support\Str::contains(strtolower($this->code . ' ' . $this->name . ' ' . $this->slug), 'pusat');
        return $hasSuperAdmin || $nameCheck;
    }
}
