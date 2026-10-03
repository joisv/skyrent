<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ReceiptResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'receipt_number' => $this->resource['receipt_number'],
            'date' => $this->resource['date'],
            'date_formatted' => $this->resource['date_formatted'],
            'admin_name' => $this->resource['admin_name'],
            'branch_name' => $this->resource['branch_name'],
            'type' => $this->resource['type'],
            'type_label' => $this->resource['type_label'],
            'booking_code' => $this->resource['booking_code'],
            'customer_name' => $this->resource['customer_name'],
            'customer_phone' => $this->resource['customer_phone'],
            'unit_name' => $this->resource['unit_name'],
            'serial_number' => $this->resource['serial_number'],
            'asset_code' => $this->resource['asset_code'],
            'rental_duration' => $this->resource['rental_duration'],
            'rental_dates' => $this->resource['rental_dates'],
            'rent_fee' => (float) $this->resource['rent_fee'],
            'deposit_fee' => (float) $this->resource['deposit_fee'],
            'fines_fee' => (float) $this->resource['fines_fee'],
            'discount_fee' => (float) $this->resource['discount_fee'],
            'total_amount' => (float) $this->resource['total_amount'],
            'paid_amount' => (float) $this->resource['paid_amount'],
            'remaining_amount' => (float) $this->resource['remaining_amount'],
            'payment_method' => $this->resource['payment_method'],
            'payment_status' => $this->resource['payment_status'],
            'cash_given' => (float) $this->resource['cash_given'],
            'cash_change' => (float) $this->resource['cash_change'],
            'deposit_status' => $this->resource['deposit_status'],
            'refund_amount' => (float) $this->resource['refund_amount'],
            'notes' => $this->resource['notes'],
            'terms_and_conditions' => $this->resource['terms_and_conditions'],
            'esc_pos_text' => $this->resource['esc_pos_text'] ?? null,
        ];
    }
}
