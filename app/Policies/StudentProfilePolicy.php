<?php

namespace App\Policies;

use App\Models\StudentProfile;
use App\Models\User;

class StudentProfilePolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        if ($user->hasRole('company') && $user->role_in_org === 'owner') {
            return true;
        }

        return $user->hasRole(['nti_admin', 'super_Admin', 'evaluator', 'mentor']);
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, StudentProfile $studentProfile): bool
    {
        if ($user->id === $studentProfile->user_id) {
            return true;
        }

        return $this->viewAny($user);
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        if (!($user->studentProfile) && $user->hasRole('student')) {
            return true;
        }

        return false;
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, StudentProfile $studentProfile): bool
    {
        if (!$studentProfile->exists) {
            return $user->id === $studentProfile->user_id;
        }

        return $user->id === $studentProfile->user_id;
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, StudentProfile $studentProfile): bool
    {
        return $user->id === $studentProfile->user_id;
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, StudentProfile $studentProfile): bool
    {
        return false;
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, StudentProfile $studentProfile): bool
    {
        return false;
    }
}