<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StudentProfile extends Model
{
    protected $fillable = [
        'user_id', 'study_program', 'year_of_study',
        'university', 'bio', 'github_url',
        'academic_declaration_confirmed',
    ];
}
