<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Call extends Model
{
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
}