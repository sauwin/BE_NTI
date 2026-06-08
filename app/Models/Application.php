<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Factories\HasFactory;

use App\Models\Call;
use App\Models\StudentProfile;
use App\Models\User;
use App\Models\Team;
use App\Models\Evaluation;
use App\Models\Mentorship;
use App\Models\Milestone;
use App\Models\Document;
use App\Models\ApplicationRevisionRequest;

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

    //Relations
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

    public function mentorships(): HasMany
    {
        return $this->hasMany(Mentorship::class);
    }

    public function milestones(): HasMany
    {
        return $this->hasMany(Milestone::class);
    }

    public function documents(): BelongsToMany
    {
        return $this->belongsToMany(Document::class, 'application_documents');
    }

    public function revisionRequests(): HasMany
    {
        return $this->hasMany(ApplicationRevisionRequest::class);
    }

    //Scopes
    public function scopeVisibleTo($query, User $user) {
        if ($user->isAdmin()) {
            return $query;
        }

        if ($user->isStudent()) {
            return $query->whereBelongsTo($user->studentProfile);
        }

        if ($user->hasRole('company')) {
            return $query
                ->with(['studentProfile.user'])
                ->whereRelation('call', 'created_by', $user->id);
        }

        if ($user->hasRole('evaluator')) {
            return $query->whereRelation('evaluations', 'evaluator_id', $user->id);
        }

        if ($user->hasRole('mentor')) {
            return $query->whereRelation('mentorships', 'mentor_id', $user->id);
        }

        throw new LogicException('Unhandled role');
    }
}
