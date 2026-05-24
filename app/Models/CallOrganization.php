<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CallOrganization extends Model
{
    protected $table = 'call_organizations';

    protected $fillable = [
        'call_id',
        'organization_id',
        'product_owner_user_id',
        'title',
        'brief',
        'budget',
        'status',
        'short_description',
        'project_goal',
        'expected_outcome',
        'detailed_technical_description',
        'required_technologies',
        'architecture_requirements',
        'integrations_apis',
        'platforms',
        'required_skills',
        'preferred_team_size',
        'required_experience',
        'expected_duration',
        'milestones',
        'deadline',
    ];

    protected $casts = [
        'budget' => 'decimal:2',
        'deadline' => 'date',
        'required_technologies' => 'array',
        'required_skills' => 'array',
    ];

    public function call(): BelongsTo
    {
        return $this->belongsTo(Call::class, 'call_id');
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class, 'organization_id');
    }

    public function productOwner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'product_owner_user_id');
    }
}