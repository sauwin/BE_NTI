<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Application extends Model
{
    protected $fillable = [
        'call_id',
        'applicant_type',
        'program_type',
        'student_profile_id',
        'team_id',
        'status',
        'submitted_at',
        'decision_at',
        'decision_by',
        'internal_notes',
    ];

    protected $casts = [
        'submitted_at' => 'datetime',
        'decision_at' => 'datetime',
    ];

    public function call(): BelongsTo
    {
        return $this->belongsTo(Call::class);
    }

    public function studentProfile(): BelongsTo
    {
        return $this->belongsTo(StudentProfile::class);
    }

    public function decisionMaker(): BelongsTo
    {
        return $this->belongsTo(User::class, 'decision_by');
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }
}