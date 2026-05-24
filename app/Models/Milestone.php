<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Milestone extends Model
{
    protected $fillable = ['application_id', 'name', 'description', 'due_date', 'status', 'completed_at'];

    protected $casts = ['due_date' => 'date', 'completed_at' => 'datetime'];

    public function application(): BelongsTo
    {
        return $this->belongsTo(Application::class);
    }

    public function documents(): BelongsToMany
    {
        return $this->belongsToMany(Document::class, 'milestone_documents');
    }
}
