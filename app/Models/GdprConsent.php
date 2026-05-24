<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GdprConsent extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'user_id',
        'purpose',
        'version',
        'ip_address',
        'consented_at',
        'withdrawn_at',
    ];

    protected $casts = [
        'consented_at' => 'datetime',
        'withdrawn_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}