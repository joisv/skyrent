<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class IphoneResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $status = $this->realtime_status ?? $this->status ?? 'ready';
        $isAvailable = in_array(strtolower($status), ['ready', 'tersedia']);

        $rental = $this->realtime_booking ?? $this->currentRental ?? $this->activeBooking ?? ($this->relationLoaded('bookings') ? $this->bookings->whereIn('status', ['confirmed', 'rented', 'disewa'])->first() : null);
        $customerName = $rental?->customer_name;
        $bookingCode = $rental?->booking_code ? ('#' . ltrim($rental->booking_code, '#')) : null;
        $returnSchedule = null;
        if ($rental && $rental->end_booking_date) {
            try {
                $endDate = \Carbon\Carbon::parse($rental->end_booking_date);
                $isToday = $endDate->isToday();
                $time = $rental->end_time ? substr($rental->end_time, 0, 5) . ' WIB' : '18:00 WIB';
                $guarantee = $rental->jaminan_type ?? null;
                $guaranteeSuffix = $guarantee ? " ({$guarantee})" : "";

                if (strtolower($status) === 'terlambat') {
                    $returnSchedule = $isToday
                        ? "Terlambat: Harusnya {$time}{$guaranteeSuffix}"
                        : "Terlambat: " . $endDate->format('d M Y') . " • {$time}{$guaranteeSuffix}";
                } else {
                    $returnSchedule = $isToday
                        ? "Kembali Hari Ini: {$time}{$guaranteeSuffix}"
                        : "Kembali: " . $endDate->format('d M Y') . " • {$time}{$guaranteeSuffix}";
                }
            } catch (\Exception $e) {
                $returnSchedule = "Kembali: {$rental->end_booking_date}";
            }
        }

        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'description' => $this->description,
            'serial_number' => $this->serial_number,
            'asset_code' => $this->asset_code,
            'gallery_id' => $this->gallery_id,
            'status' => $status,
            'status_label' => match (strtolower($status)) {
                'ready', 'tersedia' => 'Tersedia',
                'booked' => 'Dibooking',
                'rented', 'disewa' => 'Sedang Sewa',
                'terlambat', 'overdue' => 'Terlambat',
                'maintenance' => 'Perawatan',
                'transferred' => 'Ditransfer',
                'lost' => 'Hilang',
                'retired' => 'Nonaktif',
                default => ucfirst($status),
            },
            'is_available' => $isAvailable,
            'affiliate_id' => $this->affiliate_id,
            'affiliate_name' => $this->affiliate?->name,
            'branch_name' => $this->affiliate?->name,
            'storage' => $this->storage ?? '128GB',
            'color' => $this->color ?? 'Titanium / Standar',
            'battery_health' => $this->battery_health ?? 100,
            'physical_condition' => $this->physical_condition ?? 'Mulus / Normal',
            'notes' => $this->notes,
            'customer_name' => $customerName,
            'booking_code' => $bookingCode,
            'return_schedule_text' => $returnSchedule,
            'last_returned_at' => $this->last_returned_at?->toIso8601String(),
            'photo_url' => $this->gallery?->image
                ? (filter_var($this->gallery->image, FILTER_VALIDATE_URL) ? $this->gallery->image : asset('storage/' . $this->gallery->image))
                : ($this->gallery?->photo ? asset('storage/' . $this->gallery->photo) : null),
            'active_booking' => $this->whenLoaded('activeBooking', function () {
                if (! $this->activeBooking) {
                    return null;
                }
                return [
                    'id' => $this->activeBooking->id,
                    'booking_code' => $this->activeBooking->booking_code,
                    'customer_name' => $this->activeBooking->customer_name,
                    'customer_phone' => $this->activeBooking->customer_phone,
                    'start_date' => $this->activeBooking->start_booking_date,
                    'end_date' => $this->activeBooking->end_booking_date,
                    'end_time' => $this->activeBooking->end_time,
                    'status' => $this->activeBooking->status,
                ];
            }),
            'current_rental' => $this->whenLoaded('currentRental', function () {
                if (! $this->currentRental) {
                    return null;
                }
                return [
                    'id' => $this->currentRental->id,
                    'booking_code' => $this->currentRental->booking_code,
                    'customer_name' => $this->currentRental->customer_name,
                    'customer_phone' => $this->currentRental->customer_phone,
                    'start_date' => $this->currentRental->start_booking_date,
                    'end_date' => $this->currentRental->end_booking_date,
                    'end_time' => $this->currentRental->end_time,
                    'status' => $this->currentRental->status,
                ];
            }),
            'affiliate' => $this->whenLoaded('affiliate', function () {
                return $this->affiliate ? [
                    'id' => $this->affiliate->id,
                    'name' => $this->affiliate->name,
                ] : null;
            }),
            'durations' => $this->whenLoaded('durations', function () {
                return $this->durations->map(function ($duration) {
                    return [
                        'id' => $duration->id,
                        'name' => $duration->name,
                        'hours' => $duration->hours,
                        'price' => $duration->pivot->price,
                    ];
                });
            }),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
