<?php

namespace App\Services;

use App\Models\Task;
use App\Models\Call;
use App\Models\Document;
use Illuminate\Support\Facades\Storage;

/**
 * Allowed admin-side status transitions for Program B backlog tasks.
 * A task may only move forward along this chain; backward transitions
 * are not permitted (except resetting a published task to draft, which
 * stays in the company-facing endpoint).
 */

class TaskService
{
    private const ADMIN_TRANSITIONS = [
        'published'   => 'in_matching',
        'in_matching' => 'assigned',
        'assigned'    => 'in_progress',
        'in_progress' => 'closed',
    ];

    public function adminTasks(?string $status = null, int $perPage = 20)
    {
        $query = Task::with(['call', 'organization', 'productOwner'])
            ->whereHas('call', fn($q) => $q->where('program', 'b'));

        if ($status) {
            $query->where('status', $status);
        }

        return $query->orderBy('updated_at', 'desc')->paginate($perPage);
    }

    /**
     * Advance a task to the next status in the admin transition chain.
     * Optionally set product_owner_user_id when moving to assigned.
     *
     * @throws \DomainException  when the current status has no allowed next step
     */
    public function advanceStatus(Task $task, ?int $productOwnerUserId = null): Task
    {
        $current = $task->status;

        if (! array_key_exists($current, self::ADMIN_TRANSITIONS)) {
            throw new \DomainException(
                "Task cannot be advanced from status '{$current}'."
            );
        }

        $next = self::ADMIN_TRANSITIONS[$current];

        $updates = ['status' => $next];

        if ($next === 'assigned' && $productOwnerUserId) {
            $updates['product_owner_user_id'] = $productOwnerUserId;
        }

        $task->update($updates);

        return $task->fresh(['call', 'organization', 'productOwner']);
    }

    /**
     * Explicitly set any admin-managed status (for cases where you need
     * to skip or override — restricted to admin gate in the controller).
     *
     * @throws \DomainException  when $status is not in the admin-managed set
     */
    public function setAdminStatus(Task $task, string $status, ?int $productOwnerUserId = null): Task
    {
        $adminStatuses = array_merge(
            array_keys(self::ADMIN_TRANSITIONS),
            array_values(self::ADMIN_TRANSITIONS)
        );

        if (! in_array($status, array_unique($adminStatuses), true)) {
            throw new \DomainException("'{$status}' is not a valid admin-managed status.");
        }

        $updates = ['status' => $status];

        if ($status === 'assigned' && $productOwnerUserId) {
            $updates['product_owner_user_id'] = $productOwnerUserId;
        }

        $task->update($updates);

        return $task->fresh(['call', 'organization', 'productOwner']);
    }

    public function byOrganization($organizationId)
    {
        return Task::with(['call', 'organization'])
            ->where('organization_id', $organizationId)
            ->get();
    }

    public function publicTasks()
    {
        return Task::with(['call', 'organization'])
            ->where('status', 'published')
            ->whereHas('call', function ($query) {
                $query->where('program', 'b');
            })
            ->get();
    }

    public function index($userId)
    {
        return Task::with(['call', 'organization'])
            ->where('product_owner_user_id', $userId)
            ->get();
    }

    public function findWithRelations($id)
    {
        return Task::with(['organization', 'call', 'documents'])->findOrFail($id);
    }

    public function create(array $data, $user)
    {
        $organizationId = $user->organization_id ?? null;

        $call = Call::findOrFail($data['call_id']);
        if (($call->program ?? null) !== 'b') {
            throw new \DomainException('Only Program B calls can receive company tasks.');
        }

        $task = Task::create(array_merge($data, [
            'organization_id' => $organizationId,
            'product_owner_user_id' => $user->id,
            'status' => $data['status'] ?? 'draft',
        ]));

        return $task;
    }

    public function update(int $id, array $data)
    {
        $task = Task::findOrFail($id);
        $task->update($data);
        return $task;
    }

    public function delete(int $id)
    {
        $task = Task::findOrFail($id);
        $task->delete();
        return true;
    }

    public function attachDocuments(Task $task, array $uploadedFiles, $userId = null)
    {
        foreach ($uploadedFiles as $type => $file) {
            $existing = $task->documents()->where('type', $type)->first();
            if ($existing) {
                Storage::disk('local')->delete($existing->file_path);
                $task->documents()->detach($existing->id);
                $existing->delete();
            }

            $path = $file->store('documents', 'local');

            $document = Document::create([
                'uploaded_by' => $userId ?? 1,
                'type' => $type,
                'classification' => 'internal',
                'version' => 1,
                'file_path' => $path,
                'file_name' => $file->getClientOriginalName(),
                'mime_type' => $file->getClientMimeType(),
                'file_size_bytes' => $file->getSize(),
            ]);

            $task->documents()->attach($document->id);
        }

        return $task->load('documents');
    }

    public function deleteWithCallAndDocuments(int $taskId)
    {
        $taskModel = Task::with('call', 'documents')->findOrFail($taskId);
        $callModel = $taskModel->call;

        foreach ($taskModel->documents as $document) {
            if (Storage::disk('local')->exists($document->file_path)) {
                Storage::disk('local')->delete($document->file_path);
            }

            $taskModel->documents()->detach($document->id);

            $document->delete();
        }

        $taskModel->delete();

        if ($callModel) {
            $callModel->delete();
        }

        return true;
    }
}