<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use App\Http\Controllers\CallController;
use App\Http\Controllers\TaskController;
use App\Models\Document;
use App\Models\Task;

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
                'status'             => $callStatus,
                'opens_at'           => $request->input('call_opens_at'),
                'deadline_at'        => $request->input('call_deadline_at'),
                'min_team_size'      => $request->input('min_team_size', 3),
                'max_team_size'      => $request->input('max_team_size'),
                'required_documents' => $requiredDocs,
            ]);

            $callResponse = $this->callController->store($callRequest);
            
            if ($callResponse->getStatusCode() >= 400) {
                return $callResponse; 
            }
            $callData = $callResponse->getData();

            $taskRequest = new Request();
            $taskRequest->setMethod('POST');
            $taskRequest->setUserResolver(fn () => $request->user());
            
            $taskRequest->merge([
                'call_id'                        => $callData->id,
                'title'                          => $request->input('title'),
                'budget'                         => $request->input('budget'),
                'brief'                          => $request->input('brief'),
                'status'                         => $frontendStatus,
                'short_description'              => $request->input('short_description'),
                'project_goal'                   => $request->input('project_goal'),
                'expected_outcome'               => $request->input('expected_outcome'),
                'detailed_technical_description' => $request->input('detailed_technical_description'),
                'architecture_requirements'      => $request->input('architecture_requirements'),
                'platforms'                      => $request->input('platforms'),
                'deadline'                       => $request->input('deadline'),
                'required_technologies'          => $request->input('required_technologies'),
                'required_skills'                => $request->input('required_skills'),
            ]);

            $taskResponse = $this->taskController->store($taskRequest);
            
            if ($taskResponse->getStatusCode() >= 400) {
                return $taskResponse;
            }
            
            $taskModel = Task::findOrFail($taskResponse->getData()->id);

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
}