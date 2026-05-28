<?php

namespace App\Policies;

use App\Models\Document;
use App\Models\User;
use App\Policies\Concerns\AuthorizesApplicationAccess;
use Illuminate\Support\Facades\DB;

class DocumentPolicy
{
    use AuthorizesApplicationAccess;

    public function view(User $user, Document $document): bool
    {
        if ($this->isAdminOrMentor($user) || $this->isEvaluator($user)) {
            return true;
        }

        if ($document->uploaded_by === $user->id) {
            return true;
        }

        $associatedTask = DB::table('task_documents')
            ->join('tasks', 'tasks.id', '=', 'task_documents.task_id')
            ->where('task_documents.document_id', $document->id)
            ->first();

        return $associatedTask && (int) $associatedTask->product_owner_user_id === $user->id;
    }
}
