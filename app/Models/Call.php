<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

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

    public function program()
    {
    return $this->belongsTo(Program::class);
    }
}