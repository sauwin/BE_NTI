<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Evaluation extends Model
{
    protected $fillable = [
        'application_id',
        'evaluator_id',
        'status',
        'overall_score',
        'recommendation',
        'comment',
        'evaluated_at',
    ];

    protected $casts = [
        'evaluated_at' => 'datetime',
        'overall_score' => 'decimal:2',
    ];

    public function application(): BelongsTo
    {
        return $this->belongsTo(Application::class);
    }

    public function evaluator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'evaluator_id');
    }

    public function scores(): HasMany
    {
        return $this->hasMany(EvaluationCriteriaScore::class);
    }
}