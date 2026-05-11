<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StudentSkill extends Model
{
    protected $fillable = [
        'student_profile_id',
        'skill',
        'level',
    ];

    public function profile()
    {
        return $this->belongsTo(StudentProfile::class, 'student_profile_id');
    }
}