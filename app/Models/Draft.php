<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Draft extends Model
{
    protected $fillable = [
        'user_id',
        'program_type',
        'data'
    ];

    protected $casts = [
        'data' => 'array'
    ];
}
