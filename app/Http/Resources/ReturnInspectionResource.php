<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ReturnInspectionResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $securityPassed = (bool) ($this->is_icloud_signed_out && $this->is_passcode_removed && $this->is_find_my_off);
        $hardwarePassed = (bool) ($this->is_screen_responsive && $this->is_camera_normal && $this->is_buttons_normal);

        return [
            'id' => $this->id,
            'booking_id' => $this->booking_id,
            'booking_code' => $this->booking?->booking_code,
            'admin_id' => $this->admin_id,
            'admin_name' => $this->admin?->name ?? 'Admin SKYRental',
            'returned_at' => $this->returned_at?->toIso8601String() ?? $this->returned_at,
            'physical_condition' => $this->physical_condition ?? $this->condition ?? 'Mulus (Sempurna)',
            'battery_health' => (int) ($this->battery_health ?? 100),
            'security_check' => [
                'is_icloud_signed_out' => (bool) $this->is_icloud_signed_out,
                'is_passcode_removed' => (bool) $this->is_passcode_removed,
                'is_find_my_off' => (bool) $this->is_find_my_off,
                'is_security_passed' => $securityPassed,
            ],
            'hardware_check' => [
                'is_screen_responsive' => (bool) $this->is_screen_responsive,
                'is_camera_normal' => (bool) $this->is_camera_normal,
                'is_buttons_normal' => (bool) $this->is_buttons_normal,
                'is_hardware_passed' => $hardwarePassed,
            ],
            'accessories_returned' => $this->accessories_returned ?? [],
            'financials' => [
                'late_fee' => (float) $this->late_fee,
                'damage_fee' => (float) $this->damage_fee,
                'penalty_fee' => (float) ($this->penalty_fee ?? $this->total_deduction),
                'total_deduction' => (float) $this->total_deduction,
                'initial_deposit' => 0.0,
                'deposit_refunded' => 0.0,
                'customer_shortage' => (float) $this->customer_shortage,
                'refund_method' => $this->refund_method ?? 'Tunai (Kasir)',
            ],
            'late_fee' => (float) $this->late_fee,
            'damage_fee' => (float) $this->damage_fee,
            'total_deduction' => (float) $this->total_deduction,
            'deposit_refunded' => 0.0,
            'customer_shortage' => (float) $this->customer_shortage,
            'refund_method' => $this->refund_method ?? 'Tunai (Kasir)',
            'staff_notes' => $this->staff_notes,
            'status' => $this->status ?? 'completed',
            'booking' => new BookingResource($this->whenLoaded('booking')),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
