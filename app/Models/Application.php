<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Application extends Model
{
    use HasFactory;

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
        'category',
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

    public function evaluations(): HasMany
    {
        return $this->hasMany(Evaluation::class);
    }

    public function milestones(): HasMany
    {
        return $this->hasMany(Milestone::class);
    }

    public function document(): BelongToMany
    {
        return $this->belongToMany(Document::class, 'application_documents');
    }
}
