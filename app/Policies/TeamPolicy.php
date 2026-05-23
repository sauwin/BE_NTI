<?php

namespace App\Policies;

use Illuminate\Auth\Access\Response;
use App\Models\Team;
use App\Models\User;

class TeamPolicy
{
    public function view(User $user, Team $team): bool
    {
        return $team->leader_id === $user->id || $team->members()->where('user_id', $user->id)->exists();
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
