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
use App\Models\StudentProfile;
use App\Models\Call;

#[Fillable(['name', 'email', 'password'])]
#[Hidden(['remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    protected $fillable = [
        'first_name', 'last_name', 'email', 'password', 'status', 'language_preference', 'email_verified_at', 'organization_id', 'role_in_org',
    ];

    protected $hidden = ['password'];

    protected $casts = [
        'email_verified_at' => 'datetime',
    ];

    // Laravel expects getAuthPassword() to return the hash
    public function getAuthPassword(): string
    {
        return $this->password;
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

    public function hasRole($roles): bool
    {
        $roles = is_array($roles) ? $roles : [$roles];

        if ($this->relationLoaded('roles')) {
            return $this->roles->whereIn('slug', $roles)->isNotEmpty();
        }

        return $this->roles()->whereIn('slug', $roles)->exists();
    }

    public function isStudent(): bool
    {
        return $this->hasRole('student');
    }

    public function isAdmin(): bool
    {
        return $this->hasRole(['nti_admin', 'super_admin']);
    }

    public function studentProfile()
    {
        return $this->hasOne(StudentProfile::class);
    }

    public function createdCalls(): HasMany
    {
        return $this->hasMany(Call::class, 'created_by');
    }
}
