<?php

namespace App\Policies;

use App\Models\Application;
use App\Models\Mentorship;
use App\Models\User;
use App\Models\CallEvaluator;
use App\Policies\Concerns\AuthorizesApplicationAccess;
use Illuminate\Support\Facades\DB;
use Illuminate\Auth\Access\Response;

class ApplicationPolicy
{
    use AuthorizesApplicationAccess;

    protected function ownsApplicationAsStudent(User $user, Application $application): Response
    {
        if ($user->isStudent()) {
            if ($application->student_profile_id === $user->studentProfile?->id) {
                return Response::allow();
            }
            return Response::deny('It seems that you are not owner of this application.');
        }

        return Response::deny('Access denied.');
    }

    public function viewAny(User $user): Response
    {
        if ($user->isStudent()) {
            return $user->studentProfile
                ? Response::allow()
                : Response::deny('Complete your student profile first.');
        }
        
        if ($user->hasRole(['company', 'mentor', 'evaluator', 'nti_admin', 'super_admin'])) {
            return Response::allow();
            //For further verification scope exist additionally
        }

        return Response::deny('Your role does not allow to view applications');
    }

    public function viewAdminDashboard(User $user): bool
    {
        return $user->isAdmin();
    }

    public function view(User $user, Application $application): bool
    {
        \Log::info('Avavavavav asujdaju');
        if ($user->isAdmin()) {
            return true;
        }

        if ($user->isStudent()) {
            return $application->student_profile_id === $user->studentProfile?->id;
        }

        if ($user->hasRole('evaluator') || ($user->hasRole('company') && $user->role_in_org == 'evaluator')) {
            return CallEvaluator::where('call_id', $application->call->id)
                ->where('user_id', $user->id)
                ->exists();
        }

        if ($user->hasRole('company')) {
            return $application->call?->created_by === $user->id;
        }

        if ($user->hasRole('mentor')) {
            return $application->mentorships()
                ->where('mentor_id', $user->id)
                ->exists();
        }

        return false;
    }

    public function create(User $user): Response
    {
        return $user->studentProfile
            ? Response::allow()
            : Response::deny('Complete your student profile first.');
    }

    public function update(User $user, Application $application): Response
    {
        if ($user->isAdmin()) {
            return Response::allow();
        }

        if (! in_array($application->status, ['draft', 'pending_revision'])) {
            return Response::deny('Applications can be edited only in draft or pending_revision.');
        }

        return $this->ownsApplicationAsStudent($user, $application);
    }

    public function delete(User $user, Application $application): Response
    {
        if ($user->isAdmin()) {
            return Response::allow();
        }

        if ($application->status !== 'draft') {
            return Response::deny('Only drafts can be deleted.');
        }

        return $this->ownsApplicationAsStudent($user, $application);
    }

    public function submitDraft(User $user, Application $application): Response
    {
        if ($application->status !== 'draft') {
            return Response::deny("Youre can't submit this application because it is not in draft status");
        }

        return $this->ownsApplicationAsStudent($user, $application);
    }

    public function applyChanges(User $user, Application $application): Response
    {
        if ($application->status !== 'pending_revision') {
            return Response::deny("Youre can't submit changes because this application is not in pending_revision status");
        }

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

        if ($user->hasRole('company') && $user->role_in_org == 'owner') {
            return true;
        }

        if (! $user->hasRole('mentor')) {
            return false;
        }

        return Mentorship::where('application_id', $application->id)
            ->where('mentor_id', $user->id)
            ->exists();
    }

    public function finalize(User $user, Application $application): bool
    {
        return $this->isAdmin($user);
    }
}
