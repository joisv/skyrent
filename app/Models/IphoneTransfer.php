<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class IphoneTransfer extends Model
{
    /** @use HasFactory<\Database\Factories\IphoneTransferFactory> */
    use HasFactory;

    protected $fillable = [
        'iphone_id',
        'from_affiliate_id',
        'to_affiliate_id',
        'sent_by',
        'received_by',
        'status',
        'notes',
        'sent_at',
        'received_at',
    ];

    public function iphone()
    {
        return $this->belongsTo(Iphones::class);
    }

    public function fromAffiliate()
    {
        return $this->belongsTo(Affiliate::class, 'from_affiliate_id');
    }

    public function toAffiliate()
    {
        return $this->belongsTo(Affiliate::class, 'to_affiliate_id');
    }

    public function sender()
    {
        return $this->belongsTo(User::class, 'sent_by');
    }

    public function receiver()
    {
        return $this->belongsTo(User::class, 'received_by');
    }
}
