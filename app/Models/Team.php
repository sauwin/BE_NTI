<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Team extends Model
{
    protected $fillable = [
        'name',
        'leader_id',
        'description'
    ];

    public function leader()
    {
        return $this->belongsTo(User::class, 'leader_id');
    }

    public function members()
    {
        return $this->belongsToMany(User::class, 'team_members')
        ->withPivot([
            'joined_at',
            'status',
        ])
        ->withTimestamps();
    }
}
