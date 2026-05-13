<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Call extends Model
{
    use HasFactory;

    protected $fillable = [
        'program_id',
        'status',
        'opens_at',
        'deadline_at',
        'min_team_size',
        'max_team_size',
        'evaluation_criteria',
        'required_documents',
        'created_by',
    ];

    protected $casts = [
        'opens_at' => 'datetime',
        'deadline_at' => 'datetime',
        'evaluation_criteria' => 'array',
        'required_documents' => 'array',
    ];

    public function program(): BelongsTo
    {
        return $this->belongsTo(Program::class);
    }
}
