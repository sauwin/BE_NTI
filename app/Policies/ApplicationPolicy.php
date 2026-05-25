<?php

namespace App\Policies;

use App\Models\Application;
use App\Models\User;

class ApplicationPolicy
{
    private function isAdmin(User $user): bool
    {
        return $user->roles->contains(fn ($r) => in_array($r->slug, ['nti_admin', 'super_admin']));
    }

    public function view(User $user, Application $application): bool
    {
        if ($this->isAdmin($user)) {
            return true;
        }
        $profile = $user->studentProfile;

        return $profile && $application->student_profile_id === $profile->id;
    }

    public function update(User $user, Application $application): bool
    {
        if ($this->isAdmin($user)) {
            return true;
        }
        $profile = $user->studentProfile;

        return $profile && $application->student_profile_id === $profile->id;
    }

    public function delete(User $user, Application $application): bool
    {
        if ($this->isAdmin($user)) {
            return true;
        }
        $profile = $user->studentProfile;

        return $profile && $application->student_profile_id === $profile->id;
    }
}
