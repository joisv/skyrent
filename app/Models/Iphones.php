<?php

namespace App\Models;

use Carbon\Carbon;
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

    public function setIsAvailableAttribute($value): void
    {
        $this->attributes['is_available'] = (bool) $value;
    }

    public function getIsAvailableAttribute(): bool
    {
        if (array_key_exists('is_available', $this->attributes)) {
            return (bool) $this->attributes['is_available'];
        }

        return in_array(strtolower($this->status ?? 'ready'), ['ready', 'tersedia']);
    }

    /**
     * Determine if this specific physical iPhone unit is available for a requested rental period.
     *
     * @param \Carbon\Carbon|string $start
     * @param \Carbon\Carbon|string $end
     * @param int|null $excludeBookingId
     * @param bool $fresh
     * @return bool
     */
    public function isAvailableForPeriod($start, $end, ?int $excludeBookingId = null, bool $fresh = false): bool
    {
        $rawStatus = strtolower(trim($this->status ?? 'ready'));
        if (in_array($rawStatus, ['maintenance', 'perawatan', 'lost', 'hilang', 'retired', 'nonaktif', 'in_transit', 'transferred', 'mutasi'])) {
            return false;
        }

        try {
            $startDt = $start instanceof Carbon ? $start->copy() : Carbon::parse($start, 'Asia/Jakarta');
            $endDt = $end instanceof Carbon ? $end->copy() : Carbon::parse($end, 'Asia/Jakarta');
        } catch (\Exception $e) {
            return false;
        }

        if ($endDt->lte($startDt)) {
            $endDt = $startDt->copy()->addHour();
        }

        $bookings = ($fresh || !$this->relationLoaded('bookings'))
            ? $this->bookings()
                ->whereIn('status', ['pending', 'confirmed', 'rented', 'disewa'])
                ->when($excludeBookingId, fn($q) => $q->where('id', '!=', $excludeBookingId))
                ->whereDoesntHave('returns')
                ->get()
            : $this->bookings;

        $now = Carbon::now('Asia/Jakarta');

        foreach ($bookings as $b) {
            if ($excludeBookingId && (int) $b->id === (int) $excludeBookingId) {
                continue;
            }

            if (!in_array($b->status, ['pending', 'confirmed', 'rented', 'disewa'])) {
                continue;
            }

            // Check if pending booking has expired (30 minutes without payment)
            if ($b->status === 'pending') {
                $bookingCreatedAt = $b->created_at ?: ($b->created ? Carbon::parse($b->created) : null);
                if ($bookingCreatedAt && $bookingCreatedAt->copy()->addMinutes(30)->isPast()) {
                    continue;
                }
            }

            // Extract start date and time safely
            $rawStartDate = $b->start_booking_date ?: $b->requested_booking_date;
            $startDateStr = $rawStartDate instanceof Carbon ? $rawStartDate->toDateString() : (is_string($rawStartDate) ? substr($rawStartDate, 0, 10) : null);

            $rawStartTime = $b->start_time ?: $b->requested_time;
            if ($rawStartTime instanceof Carbon) {
                $startTimeStr = $rawStartTime->format('H:i');
            } elseif (is_string($rawStartTime) && strlen($rawStartTime) > 0) {
                if (strlen($rawStartTime) >= 19 && strpos($rawStartTime, ' ') !== false) {
                    $startTimeStr = substr(explode(' ', $rawStartTime)[1], 0, 5);
                } else {
                    $startTimeStr = substr(trim($rawStartTime), 0, 5);
                }
            } else {
                $startTimeStr = '00:00';
            }

            try {
                $bStart = Carbon::parse("{$startDateStr} {$startTimeStr}", 'Asia/Jakarta');
            } catch (\Exception $e) {
                $bStart = $rawStartDate instanceof Carbon ? $rawStartDate->copy() : Carbon::parse($rawStartDate, 'Asia/Jakarta');
            }

            // Extract end date and time safely
            $rawEndDate = $b->end_booking_date;
            $rawEndTime = $b->end_time;
            if ($rawEndDate) {
                $endDateStr = $rawEndDate instanceof Carbon ? $rawEndDate->toDateString() : (is_string($rawEndDate) ? substr($rawEndDate, 0, 10) : null);
                if ($rawEndTime instanceof Carbon) {
                    $endTimeStr = $rawEndTime->format('H:i');
                } elseif (is_string($rawEndTime) && strlen($rawEndTime) > 0) {
                    if (strlen($rawEndTime) >= 19 && strpos($rawEndTime, ' ') !== false) {
                        $endTimeStr = substr(explode(' ', $rawEndTime)[1], 0, 5);
                    } else {
                        $endTimeStr = substr(trim($rawEndTime), 0, 5);
                    }
                } else {
                    $endTimeStr = '23:59';
                }

                try {
                    $bEnd = Carbon::parse("{$endDateStr} {$endTimeStr}", 'Asia/Jakarta');
                } catch (\Exception $e) {
                    $bEnd = $bStart->copy()->addHours((int) ($b->duration ?: 24));
                }
            } else {
                $bEnd = $bStart->copy()->addHours((int) ($b->duration ?: 24));
            }

            // If unit is currently rented/disewa and not returned, and current time passed scheduled end,
            // the unit remains physically out until now.
            if (in_array($b->status, ['rented', 'disewa']) && $bEnd->lt($now)) {
                $bEnd = $now;
            }

            // Check interval overlap: [bStart, bEnd] overlaps with [startDt, endDt]
            if ($bStart->lt($endDt) && $bEnd->gt($startDt)) {
                return false;
            }
        }

        return true;
    }
}
