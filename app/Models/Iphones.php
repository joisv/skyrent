<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Iphones extends Model
{
    /** @use HasFactory<\Database\Factories\IphonesFactory> */
    use HasFactory;
    protected $fillable = [
        'name',
        'description',
    ];

    public function gallery()
    {
        return $this->belongsTo(Gallery::class);
    }
}
