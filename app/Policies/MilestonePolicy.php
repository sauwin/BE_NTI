<?php

namespace App\Policies;

use App\Models\Application;
use App\Models\Milestone;
use App\Models\User;
use App\Policies\Concerns\AuthorizesApplicationAccess;

class MilestonePolicy
{
    use AuthorizesApplicationAccess;

    public function viewAny(User $user, Application $application): bool
    {
        return $this->isAdminOrMentor($user) || $this->hasApplicationStake($user, $application);
    }

    public function view(User $user, Milestone $milestone): bool
    {
        return $this->viewAny($user, $milestone->application);
    }

    public function create(User $user, Application $application): bool
    {
        return $this->isAdminOrMentor($user);
    }

    public function update(User $user, Milestone $milestone): bool
    {
        return $this->isAdminOrMentor($user);
    }

    public function uploadDocument(User $user, Milestone $milestone): bool
    {
        return $this->viewAny($user, $milestone->application);
    }
}
