<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BookingPaymentResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'payment_code' => $this->payment_code ?? 'PAY-' . str_pad((string) $this->id, 6, '0', STR_PAD_LEFT),
            'booking_id' => $this->booking_id,
            'booking_code' => $this->booking?->booking_code,
            'customer_name' => $this->booking?->customer_name,
            'customer_phone' => $this->booking?->customer_phone,
            'payment_id' => $this->payment_id,
            'payment_method' => $this->payment?->name ?? 'Tunai (Cash)',
            'amount' => (float) $this->amount,
            'pay' => (float) ($this->pay ?? $this->amount),
            'change' => (float) ($this->change ?? 0),
            'type' => $this->type,
            'type_label' => match ($this->type) {
                'dp' => 'Uang Muka (DP)',
                'payment', 'pelunasan' => 'Pelunasan Sewa',
                'deposit' => 'Uang Jaminan (Deposit)',
                'deposit_refund', 'refund' => 'Pengembalian (Refund)',
                'penalty' => 'Denda Keterlambatan/Kerusakan',
                'extend' => 'Perpanjangan Sewa',
                default => ucfirst($this->type),
            },
            'paid_at' => $this->paid_at?->toIso8601String() ?? $this->created_at?->toIso8601String(),
            'paid_at_formatted' => $this->paid_at?->format('d M Y, H:i') ?? $this->created_at?->format('d M Y, H:i'),
            'note' => $this->note,
            'reference_number' => $this->reference_number,
            'user' => $this->user ? [
                'id' => $this->user->id,
                'name' => $this->user->name,
            ] : null,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
