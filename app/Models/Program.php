<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Program extends Model
{
    use HasFactory;
    
    protected $fillable = [
        'code',
        'type',
        'is_active',
        'config',
    ];

    public function calls()
    {
    return $this->hasMany(Call::class);
    }
}