<?php

namespace App\Policies;

use Illuminate\Auth\Access\Response;
use App\Models\Organization;
use App\Models\User;

class OrganizationPolicy
{
    public function viewAny(User $user): bool
    {
        return false;
    }

    public function view(User $user, Organization $organization): bool
    {
        return $user->organization?->id === $organization->id;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, Organization $organization): bool
    {
        return $user->hasOrgRole($organization->id, 'owner');
    }

    public function manageMembers(User $user, Organization $organization): bool
    {
        return $user->organization_id === $organization->id
            && $user->hasOrgRole($organization->id, 'owner');
    }

    public function forceDelete(User $user, Organization $organization): bool
    {
        return false;
    }
}
