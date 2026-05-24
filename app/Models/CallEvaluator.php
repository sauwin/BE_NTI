<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CallEvaluator extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'call_id',
        'user_id',
        'assigned_at',
    ];

    protected $casts = [
        'assigned_at' => 'datetime',
    ];

    public function call(): BelongsTo
    {
        return $this->belongsTo(Call::class);
    }

    public function evaluator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}