<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MentorProfile extends Model
{
    protected $fillable = ['user_id', 'bio', 'expertise_areas', 'available'];

    protected $casts = [
        'expertise_areas' => 'array',
        'available' => 'boolean',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}