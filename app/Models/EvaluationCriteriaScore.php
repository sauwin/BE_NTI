<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

use App\Models\EvaluationCriterion;

class EvaluationCriteriaScore extends Model
{
    protected $fillable = [
        'evaluation_id',
        'criterion_id',
        'score',
        'weight_at_moment',
        'comment',
    ];

    protected $casts = [
        'score' => 'decimal:2',
        'weight_at_moment' => 'decimal:2',
    ];

    public function evaluation(): BelongsTo
    {
        return $this->belongsTo(Evaluation::class);
    }

    public function criterion(): BelongsTo
    {
        return $this->belongsTo(EvaluationCriterion::class);
    }
}
