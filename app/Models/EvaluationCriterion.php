<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Call;

class EvaluationCriterion extends Model
{
    protected $fillable = [
        'call_id',
        'slug',
        'title',
        'weight',
        'comment'
    ];

    public function call(): BelongsTo
    {
        return $this->belongsTo(Call::class);
    }
}
