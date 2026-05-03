<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

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
        'decision_at'  => 'datetime',
    ];
}