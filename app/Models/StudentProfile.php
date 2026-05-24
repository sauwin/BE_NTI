<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class StudentProfile extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'study_program',
        'year_of_study',
        'university',
        'bio',
        'github_url',
        'academic_declaration_confirmed',
        'cv_document_id',
    ];

    protected $casts = [
        'academic_declaration_confirmed' => 'boolean',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function skills()
    {
        return $this->hasMany(StudentSkill::class);
    }
}