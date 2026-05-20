<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

use App\Http\Models\User;

class Role extends Model
{
    protected $fillable = [
        'name',
        'slug',
        'description',
    ];

    public function users()
    {
        return $this->belongsToMany(User::class, 'user_roles')
            ->withPivot('granted_by', 'granted_at');
    }
}
