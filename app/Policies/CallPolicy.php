<?php

namespace App\Policies;

use App\Models\Call;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class CallPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return false;
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, Call $call): bool
    {
        return false;
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return false;
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Call $call): bool
    {
        return $user->hasRole(['nti_admin', 'super_admin']);
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Call $call): bool
    {
        return false;
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, Call $call): bool
    {
        return false;
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, Call $call): bool
    {
        return false;
    }

    /**
     * Determine whether the user can view call evaluation criteria.
     */
    public function viewCriteria(User $user, Call $call): bool
    {
        if ($user->hasRole('evaluator')) {
            return $call->evaluators()->whereKey($user->id)->exists();
        }

        return $user->hasRole(['admin', 'super_admin']);
    }
}
