<?php

namespace App\Policies;

use App\Models\Mentorship;
use App\Models\User;
use App\Policies\Concerns\AuthorizesApplicationAccess;

class MentorshipPolicy
{
    use AuthorizesApplicationAccess;

    public function view(User $user, Mentorship $mentorship): bool
    {
        return $this->isAdmin($user) || (int) $mentorship->mentor_id === $user->id;
    }

    public function create(User $user): bool
    {
        return $this->isAdmin($user);
    }

    public function delete(User $user, Mentorship $mentorship): bool
    {
        if ($this->isAdmin($user)) {
            return true;
        }

        return $user->hasRole('mentor') && (int) $mentorship->mentor_id === $user->id;
    }

    public function manageConsultations(User $user, Mentorship $mentorship): bool
    {
        return (int) $mentorship->mentor_id === $user->id;
    }
}
