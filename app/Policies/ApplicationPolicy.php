<?php

namespace App\Policies;

use App\Models\Application;
use App\Models\Mentorship;
use App\Models\User;
use App\Policies\Concerns\AuthorizesApplicationAccess;

class ApplicationPolicy
{
    use AuthorizesApplicationAccess;

    public function view(User $user, Application $application): bool
    {
        return $this->isAdminOrMentor($user) || $this->hasApplicationStake($user, $application);
    }

    public function create(User $user): bool
    {
        return $user->studentProfile !== null;
    }

    public function update(User $user, Application $application): bool
    {
        if ($this->isAdmin($user)) {
            return true;
        }

        return $this->ownsApplicationAsStudent($user, $application);
    }

    public function delete(User $user, Application $application): bool
    {
        return $this->update($user, $application);
    }

    public function submit(User $user, Application $application): bool
    {
        return $this->ownsApplicationAsStudent($user, $application);
    }

    public function uploadDocument(User $user, Application $application): bool
    {
        return $this->isAdminOrMentor($user) || $this->ownsApplicationAsStudent($user, $application);
    }

    public function updateStatus(User $user, Application $application): bool
    {
        if ($this->isAdmin($user)) {
            return true;
        }

        if (! $user->hasRole('mentor')) {
            return false;
        }

        return Mentorship::where('application_id', $application->id)
            ->where('mentor_id', $user->id)
            ->exists();
    }
}
