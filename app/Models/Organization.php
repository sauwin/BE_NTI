<?php

namespace App\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class Organization extends Model
{
    protected $fillable = [
        'name', 'registration_number', 'sector',
        'description', 'website', 'logo_path',
        'status', 'is_public_partner',
    ];

    public function members()
    {
        return $this->hasMany(User::class, 'organization_id');
    }
}
