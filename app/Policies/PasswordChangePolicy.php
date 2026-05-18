<?php

namespace App\Policies;

use App\Models\User;

class PasswordChangePolicy
{
    public function canChangeOwnPassword(User $user): bool
    {
        return !$user->roles()->whereIn('slug', ['nti_admin', 'super_admin'])->exists();
    }

    public function canRequestReset(User $targetUser, User $admin): bool
    {
        $adminRoles = $admin->roles()->pluck('slug');

        if (!$adminRoles->contains('super_admin')) {
            return false;
        }

        return !$targetUser->roles()->where('slug', 'super_admin')->exists();
    }

    public function canInitiateSuperAdminReset(): bool
    {
        return false;
    }
}