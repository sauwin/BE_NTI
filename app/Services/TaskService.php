<?php

namespace App\Services;

use App\Models\Task;
use App\Models\Call;
use App\Models\Document;
use Illuminate\Support\Facades\Storage;

class TaskService
{
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
