<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Expense extends Model
{
    use HasFactory;

    protected $fillable = [
        'affiliate_id',
        'user_id',
        'amount',
        'category',
        'notes',
        'spent_at',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'spent_at' => 'datetime',
    ];

    public function affiliate()
    {
        return $this->belongsTo(Affiliate::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function scopeForAffiliate($query, $affiliateId)
    {
        if ($affiliateId) {
            return $query->where('affiliate_id', $affiliateId);
        }
        return $query;
    }

    public function scopeByDateRange($query, $startDate, $endDate)
    {
        return $query->whereBetween('spent_at', [$startDate, $endDate]);
    }
}
