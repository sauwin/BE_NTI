<?php

namespace App\Policies;

use App\Models\Evaluation;
use App\Models\User;

class EvaluationPolicy
{
    private function canEvaluate(User $user): bool
    {
        return $user->hasRole(['evaluator', 'nti_admin', 'super_admin']);
    }

    public function viewAny(User $user): bool
    {
        return $this->canEvaluate($user);
    }

    public function view(User $user, Evaluation $evaluation): bool
    {
        if ($user->hasRole(['nti_admin', 'super_admin'])) {
            return true;
        }

        return $this->canEvaluate($user) && $evaluation->evaluator_id === $user->id;
    }

    public function create(User $user): bool
    {
        return $this->canEvaluate($user);
    }

    public function update(User $user, Evaluation $evaluation): bool
    {
        if ($user->hasRole(['nti_admin', 'super_admin'])) {
            return true;
        }

        return $this->canEvaluate($user) && $evaluation->evaluator_id === $user->id;
    }
}