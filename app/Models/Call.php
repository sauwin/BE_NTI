<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Call extends Model
{
    use HasFactory;

    protected $fillable = [
        'program_id',
        'name',
        'status',
        'opens_at',
        'deadline_at',
        'min_team_size',
        'max_team_size',
        'evaluation_criteria',
        'required_documents',
        'created_by',
        'evaluation_scheduled_at',
    ];

    protected $casts = [
        'opens_at' => 'datetime',
        'deadline_at' => 'datetime',
        'evaluation_criteria' => 'array',
        'required_documents' => 'array',
        'evaluation_scheduled_at' => 'datetime',
    ];

    public function program(): BelongsTo
    {
        return $this->belongsTo(Program::class);
    }

    public function evaluators(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'call_evaluators')
            ->withPivot('assigned_at')
            ->withTimestamps(false);
    }

    public function applications(): HasMany
    {
        return $this->hasMany(Application::class);
    }
}
