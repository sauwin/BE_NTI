<?php

namespace App\Policies;

use App\Models\User;

class ReportingPolicy
{
    /**
     * Determine whether the user can view the dashboard stats.
     */
    public function viewDashboardStats(User $user): bool
    {
        return $user->roles()
            ->whereIn('slug', ['nti_admin', 'super_admin'])
            ->exists();
    }
}