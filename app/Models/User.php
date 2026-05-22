<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

use App\Models\Organization;
use App\Models\Team;

#[Fillable(['name', 'email', 'password'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    protected $fillable = [
        'first_name', 'last_name', 'email', 'password_hash', 'status', 'language_preference', 'email_verified_at', 'organization_id', 'role_in_org',
    ];

    protected $hidden = ['password_hash'];

    protected $casts = [
        'email_verified_at' => 'datetime',
    ];

    // Laravel expects getAuthPassword() to return the hash
    public function getAuthPassword(): string
    {
        return $this->password_hash;
    }

    public function article()
    {
        return $this->hasMany(NewsArticle::class, 'author_id');
    }

    public function roles()
    {
        return $this->belongsToMany(Role::class, 'user_roles')
            ->withPivot('granted_by', 'granted_at');
    }

    public function organization()
    {
        return $this->belongsTo(Organization::class);
    }

    public function hasOrgRole($organizationId, $role): bool
    {
        return $this->organization_id === $organizationId && $this->role_in_org === $role;
    }

    public function teams()
    {
        return $this->belongsToMany(Team::class, 'team_members');
    }
}
