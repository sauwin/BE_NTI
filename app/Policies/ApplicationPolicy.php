<?php

namespace App\Policies;

use App\Models\Application;
use App\Models\Mentorship;
use App\Models\User;
use App\Policies\Concerns\AuthorizesApplicationAccess;
use Illuminate\Support\Facades\DB;

class ApplicationPolicy
{
    use AuthorizesApplicationAccess;

    public function view(User $user, Application $application): bool
    {
        if ($this->isAdminOrMentor($user)) {
            return true;
        }

        if ($this->hasApplicationStake($user, $application)) {
            return true;
        }

        $application->loadMissing('call.task');

        if ($user->hasRole('evaluator')) {
            $isAssigned = DB::table('call_evaluators')
                ->where('user_id', $user->id)
                ->where('call_id', $application->call_id)
                ->exists();

            return $isAssigned && $application->status === 'under_evaluation';
        }

        if ($user->hasRole('student')) {
            return false;
        }

        return DB::table('users')
            ->where('id', $user->id)
            ->where('role_in_org', 'owner')
            ->where('organization_id', $application->call->task->organization_id)
            ->exists();
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

        $application->loadMissing('call.task');

        if ($user->hasRole('mentor')) {
            $isAssigned = Mentorship::where('application_id', $application->id)
            ->where('mentor_id', $user->id)
            ->exists();

            return $isAssigned;
        }

        return DB::table('users')
            ->where('id', $user->id)
            ->where('role_in_org', 'owner')
            ->where('organization_id', $application->call->task->organization_id)
            ->exists();
    }
}
