<?php

namespace App\Policies\Concerns;

use App\Models\Application;
use App\Models\User;

trait AuthorizesApplicationAccess
{
    protected function isAdmin(User $user): bool
    {
        return $user->hasRole(['nti_admin', 'super_admin']);
    }

    protected function isAdminOrMentor(User $user): bool
    {
        return $user->hasRole(['nti_admin', 'super_admin', 'mentor']);
    }

    protected function ownsApplicationAsStudent(User $user, Application $application): bool
    {
        $profile = $user->studentProfile;

        return $profile && $application->student_profile_id === $profile->id;
    }

    protected function hasApplicationStake(User $user, Application $application): bool
    {
        if ($this->ownsApplicationAsStudent($user, $application)) {
            return true;
        }

        if (! $application->team_id) {
            return false;
        }

        return $application->team()
            ->whereHas('members', fn ($q) => $q->where('users.id', $user->id))
            ->exists();
    }
}
