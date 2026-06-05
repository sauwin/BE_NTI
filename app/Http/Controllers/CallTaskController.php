<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use App\Http\Controllers\CallController;
use App\Http\Controllers\TaskController;
use App\Models\Document;
use App\Models\Task;
use App\Models\Call;

/**
 * @tags Call Management
 * Composite endpoints for atomic operations combining call orchestration, task assignments, and internal attachment management within single database transactions.
 */
class CallTaskController extends Controller
{
    protected $callController;
    protected $taskController;

    public function __construct(CallController $callController, TaskController $taskController)
    {
        $this->callController = $callController;
        $this->taskController = $taskController;
    }

    public function storeCallWithTask(Request $request)
    {
        $frontendStatus = $request->input('status', 'draft');

        return DB::transaction(function () use ($request, $frontendStatus) {
            
            $callStatus = ($frontendStatus === 'published') ? 'open' : 'draft';
            
            $requiredDocs = $request->input('required_documents');
            if (is_string($requiredDocs)) {
                $requiredDocs = json_decode($requiredDocs, true);
            }

            $callRequest = new Request();
            $callRequest->setMethod('POST');
            $callRequest->setUserResolver(fn () => $request->user());
            $callRequest->merge([
                'program_id'         => 'program_b', 
                'name'               => $request->input('title'),
                'short_description'  => $request->input('short_description'),
                'status'             => $callStatus,
                'start_date'         => now()->format('Y-m-d'),
                'end_date'           => $request->input('deadline'),
                'required_documents' => $requiredDocs ?? [],
            ]);

            $callResponse = $this->callController->store($callRequest);
            $callData = $callResponse->getData(true)['call'] ?? $callResponse->getData(true);
            $callId = $callData['id'];

            $taskRequest = new Request();
            $taskRequest->setMethod('POST');
            $taskRequest->setUserResolver(fn () => $request->user());
            $taskRequest->merge(array_merge($request->all(), [
                'call_id' => $callId,
            ]));

            $taskResponse = $this->taskController->store($taskRequest);
            $taskData = $taskResponse->getData(true)['task'] ?? $taskResponse->getData(true);
            $taskId = $taskData['id'];

            $taskModel = Task::findOrFail($taskId);

            if ($request->hasFile('files')) {
                $userId = $request->user()->id ?? 1;
                $uploadedFiles = $request->file('files'); 

                foreach ($uploadedFiles as $type => $file) {
                    $existing = $taskModel->documents()->where('type', $type)->first();
                    if ($existing) {
                        Storage::disk('local')->delete($existing->file_path);
                        $taskModel->documents()->detach($existing->id);
                        $existing->delete();
                    }

                    $path = $file->store('documents', 'local');

                    $document = Document::create([
                        'uploaded_by' => $userId,
                        'type' => $type,
                        'classification' => 'internal',
                        'version' => 1,
                        'file_path' => $path,
                        'file_name' => $file->getClientOriginalName(),
                        'mime_type' => $file->getClientMimeType(),
                        'file_size_bytes' => $file->getSize(),
                    ]);

                    $taskModel->documents()->attach($document->id);
                }
            }

            return response()->json([
                'message' => 'Call, Task and all Documents successfully created inside a single transaction!',
                'call'    => $callData,
                'task'    => $taskModel->load('documents') 
            ], 201);
            
        });
    }

    public function updateCallWithTask(Request $request, $id)
    {
        $taskModel = Task::with('call', 'documents')->findOrFail($id);
        $callModel = $taskModel->call;

        if (!$callModel) {
            return response()->json(['message' => 'Associated Call not found for this Task.'], 404);
        }

        $frontendStatus = $request->input('status', 'draft');

        return DB::transaction(function () use ($request, $taskModel, $callModel, $frontendStatus) {
            
            $callStatus = ($frontendStatus === 'published') ? 'open' : 'draft';
            
            $requiredDocs = $request->input('required_documents');
            if (is_string($requiredDocs)) {
                $requiredDocs = json_decode($requiredDocs, true);
            }

            $callModel->update([
                'name'               => $request->input('title', $callModel->name),
                'short_description'  => $request->input('short_description', $callModel->short_description),
                'status'             => $callStatus,
                'end_date'           => $request->input('deadline', $callModel->end_date),
                'required_documents' => $requiredDocs ?? $callModel->required_documents,
            ]);

            $taskModel->update($request->all());

            if ($request->hasFile('files')) {
                $userId = $request->user()->id ?? 1;
                $uploadedFiles = $request->file('files'); 

                foreach ($uploadedFiles as $type => $file) {
                    $existing = $taskModel->documents()->where('type', $type)->first();
                    if ($existing) {
                        Storage::disk('local')->delete($existing->file_path);
                        $taskModel->documents()->detach($existing->id);
                        $existing->delete();
                    }

                    $path = $file->store('documents', 'local');

                    $document = Document::create([
                        'uploaded_by' => $userId,
                        'type' => $type,
                        'classification' => 'internal',
                        'version' => 1,
                        'file_path' => $path,
                        'file_name' => $file->getClientOriginalName(),
                        'mime_type' => $file->getClientMimeType(),
                        'file_size_bytes' => $file->getSize(),
                    ]);

                    $taskModel->documents()->attach($document->id);
                }
            }

            return response()->json([
                'message' => 'Call, Task and Documents successfully updated inside a single transaction!',
                'call'    => $callModel,
                'task'    => $taskModel->load('documents')
            ], 200);
        });
    }

    public function deleteCallWithTask($id)
    {
        $taskModel = Task::with('call', 'documents')->findOrFail($id);
        $callModel = $taskModel->call;

        return DB::transaction(function () use ($taskModel, $callModel) {
            
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

            return response()->json([
                'message' => 'Task, associated Call and all connected documents successfully deleted!'
            ], 200);
        });
    }
}