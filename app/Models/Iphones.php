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
    ];

    public function durations()
    {
        return $this->belongsToMany(Duration::class)
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
}
