<?php

namespace App\Policies;

use Illuminate\Auth\Access\Response;
use App\Models\Team;
use App\Models\User;

class TeamPolicy
{
    public function view(User $user, Team $team): bool
    {

        if ($user->hasRole(['mentor', 'evaluator', 'nti_admin', 'super_admin', 'company'])) {
            return true;
        }

        return $team->leader_id === $user->id || $user->teams->contains($team->id);
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, Team $team): bool
    {
        return $team->leader_id === $user->id;
    }

    public function delete(User $user, Team $team): bool
    {
        return $team->leader_id === $user->id;
    }

    public function manageMembers(User $user, Team $team): bool
    {
        return $team->leader_id === $user->id;
    }
}
