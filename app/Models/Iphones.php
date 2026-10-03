<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Iphones extends Model
{
    /** @use HasFactory<\Database\Factories\IphonesFactory> */
    use HasFactory;
    protected $fillable = [
        'name',
        'description',
        'gallery_id',
        'user_id',
        'slug',
        'created',
        'booked',
        'serial_number',
        'asset_code',
        'status',
        'affiliate_id',
    ];

    public function bookings()
    {
        return $this->hasMany(Booking::class, 'iphone_id');
    }

    // Indirect relation ke Revenue
    public function revenues()
    {
        return $this->hasManyThrough(
            Revenue::class,
            Booking::class,
            'iphone_id',     // Foreign key di Booking
            'booking_id',    // Foreign key di Revenue
            'id',            // Local key di Iphone
            'id'             // Local key di Booking
        );
    }

    // public function durations()
    // {
    //     return $this->belongsToMany(Duration::class)
    //         ->withPivot('price')
    //         ->withTimestamps();
    // }

    public function durations()
    {
        return $this->belongsToMany(
            Duration::class,
            'duration_iphones', // pivot table
            'iphones_id',       // foreign key iphone
            'duration_id'       // foreign key duration
        )
            ->withPivot('price')
            ->withTimestamps();
    }

    public function gallery()
    {
        return $this->belongsTo(Gallery::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function reviews()
    {
        return $this->hasMany(Review::class);
    }

    public function sliders()
    {
        return $this->hasMany(Slider::class);
    }

    public function affiliate()
    {
        return $this->belongsTo(Affiliate::class);
    }

    public function transfers()
    {
        return $this->hasMany(IphoneTransfer::class, 'iphone_id');
    }

    public function activeBooking()
    {
        return $this->hasOne(Booking::class, 'iphone_id')->ofMany(
            ['id' => 'max'],
            function ($query) {
                $query->where('status', 'rented');
            }
        );
    }

    public function currentRental()
    {
        return $this->hasOne(Booking::class, 'iphone_id')->ofMany(
            ['id' => 'max'],
            function ($query) {
                $query->where('status', 'rented');
            }
        );
    }

    public function rentalHistory()
    {
        return $this->hasMany(Booking::class, 'iphone_id')
            ->orderBy('start_booking_date', 'desc')
            ->latest('id');
    }

    public function upcomingBookings()
    {
        return $this->hasMany(Booking::class, 'iphone_id')
            ->whereIn('status', ['confirmed', 'pending'])
            ->where('start_booking_date', '>=', now()->toDateString())
            ->orderBy('start_booking_date', 'asc');
    }

    public function returns()
    {
        return $this->hasManyThrough(
            ReturnIphone::class,
            Booking::class,
            'iphone_id',
            'booking_id',
            'id',
            'id'
        );
    }

    // Scopes
    public function scopeReady($query)
    {
        return $query->whereIn('status', ['ready', 'tersedia']);
    }

    public function scopeRented($query)
    {
        return $query->whereIn('status', ['rented', 'disewa']);
    }

    public function scopeMaintenance($query)
    {
        return $query->where('status', 'maintenance');
    }

    public function scopeBooked($query)
    {
        return $query->where('status', 'booked');
    }

    public function scopeSearch($query, ?string $term)
    {
        if (empty($term)) {
            return $query;
        }

        return $query->where(function ($q) use ($term) {
            $q->where('name', 'like', "%{$term}%")
                ->orWhere('asset_code', 'like', "%{$term}%")
                ->orWhere('serial_number', 'like', "%{$term}%")
                ->orWhere('description', 'like', "%{$term}%");
        });
    }

    // Accessors
    public function getFullNameAttribute(): string
    {
        $storage = $this->storage ? " {$this->storage}" : '';
        return trim("{$this->name}{$storage}");
    }

    public function getIsAvailableAttribute(): bool
    {
        return in_array(strtolower($this->status ?? 'ready'), ['ready', 'tersedia']);
    }
}
