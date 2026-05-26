<?php

namespace App\Policies;

use App\Models\Task;
use App\Models\User;

class TaskPolicy
{
    public function view(User $user, Task $task): bool
    {
        if ((int) $task->product_owner_user_id === $user->id) {
            return true;
        }

        if ($user->organization_id && (int) $task->organization_id === (int) $user->organization_id) {
            return true;
        }

        return $task->status === 'published';
    }

    public function create(User $user): bool
    {
        return $user->organization_id !== null;
    }

    public function update(User $user, Task $task): bool
    {
        return (int) $task->product_owner_user_id === $user->id;
    }

    public function delete(User $user, Task $task): bool
    {
        return (int) $task->product_owner_user_id === $user->id;
    }
}
