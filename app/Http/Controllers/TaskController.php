<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreTaskRequest;
use App\Http\Requests\UpdateTaskRequest;
use App\Models\Application;
use App\Models\Task;
use App\Models\User;
use App\Services\AuditService;
use App\Services\CallService;
use App\Services\TaskService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * @tags Task Management
 * Endpoints for managing technical task lifecycles, including drafting and publishing project requirements, defining technical architectures, budgeting, and assigning product ownership within specific organizational programs.
 */
class TaskController extends Controller
{
    protected $taskService;

    protected $callService;

    public function __construct(TaskService $taskService, CallService $callService)
    {
        $this->taskService = $taskService;
        $this->callService = $callService;
    }

    /**
     * Create a new Call and Task together in a single transaction.
     */
    public function storeCallWithTask(Request $request)
    {
        $this->authorize('create', Task::class);

        $frontendStatus = $request->input('status', 'draft');

        return DB::transaction(function () use ($request, $frontendStatus) {
            $callStatus = ($frontendStatus === 'published') ? 'open' : 'draft';

            $callData = [
                'program_type' => $request->input('program_type', 'b'),
                'title' => $request->input('title'),
                'short_description' => $request->input('short_description'),
                'status' => $callStatus,
                'min_team_size' => $request->input('min_team_size'),
                'max_team_size' => $request->input('max_team_size'),
                'opens_at' => $request->input('opens_at'),
                'deadline_at' => $request->input('deadline_at'),
                'required_documents' => $request->input('required_documents'),
                'form_config' => $request->input('form_config'),
            ];

            $call = $this->callService->create($callData, $request->user()->id ?? 1);

            $taskData = array_merge($request->all(), [
                'call_id' => $call->id,
            ]);

            $task = $this->taskService->create($taskData, $request->user());
            $admins = User::whereHas('roles', fn ($q) => $q->whereIn('slug', ['nti_admin', 'super_admin']))->get();
            foreach ($admins as $admin) {
                NotificationController::log($admin->id, $admin->email, 'task_created', 'New Program B task created', ['task_id' => $task->id, 'call_id' => $call->id]);
            }
            if ($task->organization) {
                foreach ($task->organization->members as $member) {
                    NotificationController::log($member->id, $member->email, 'task_created', 'Your task has been successfully created.', ['task_id' => $task->id]);
                }
            }
            if ($request->hasFile('files')) {
                $this->taskService->attachDocuments($task, $request->file('files'), $request->user()->id ?? 1);
            }

            return response()->json([
                'message' => 'Call, Task and all Documents successfully created inside a single transaction!',
                'call' => $call,
                'task' => $task->load('documents'),
            ], 201);
        });
    }

    /**
     * Update Call and Task together in a single transaction.
     */
    public function updateCallWithTask(Request $request, $id)
    {
        $taskModel = Task::with('call', 'documents')->findOrFail($id);
        $callModel = $taskModel->call;

        if (! $callModel) {
            return response()->json(['message' => 'Associated Call not found for this Task.'], 404);
        }

        $frontendStatus = $request->input('status', 'draft');

        return \DB::transaction(function () use ($request, $taskModel, $callModel, $frontendStatus) {
            $callStatus = ($frontendStatus === 'published') ? 'open' : 'draft';

            $callData = [
                'title' => $request->input('title', $callModel->name),
                'short_description' => $request->input('short_description', $callModel->short_description),
                'status' => $callStatus,
                'min_team_size' => $request->input('min_team_size'),
                'max_team_size' => $request->input('max_team_size'),
                'opens_at' => $request->input('opens_at'),
                'deadline_at' => $request->input('deadline_at'),
                'required_documents' => $request->input('required_documents') ?? $callModel->required_documents,
            ];

            $this->callService->update($callModel->id, $callData);

            $this->taskService->update($taskModel->id, $request->all());

            if ($request->hasFile('files')) {
                $this->taskService->attachDocuments($taskModel, $request->file('files'), $request->user()->id ?? 1);
            }

            return response()->json([
                'message' => 'Call, Task and Documents successfully updated inside a single transaction!',
                'call' => $this->callService->find($callModel->id),
                'task' => $taskModel->load('documents'),
            ], 200);
        });
    }

    /**
     * Update only the status of a Call and its associated Task.
     */
    public function updateCallWithTaskStatus(Request $request, $id)
    {
        $request->validate([
            'status' => 'required|in:draft,published',
        ]);

        $task = Task::with('call')->findOrFail($id);
        $this->authorize('update', $task);

        $call = $task->call;
        if (! $call) {
            return response()->json(['message' => 'Associated call not found for this Task.'], 404);
        }

        $task->status = $request->input('status');
        $task->save();
        $call->status = $request->input('status') === 'published' ? 'open' : 'draft';
        $call->save();

        if ($task->productOwner) {
            NotificationController::log($task->productOwner->id, $task->productOwner->email, 'task_status_changed', 'Your task "'.$task->title.'" is now '.($task->status), ['task_id' => $task->id]);
        }

        return response()->json(['message' => 'Status updated', 'call' => $call, 'task' => $task]);
    }

    public function deleteCallWithTask(Request $request, Task $task)
    {
        return \DB::transaction(function () use ($request, $task) {
            $task->documents()->delete();

            $this->taskService->delete($task->id);
            $this->callService->delete($task->call->id);

            return response()->json([
                'message' => 'Call, Task and Documents deleted!',
            ]);
        });
    }

    /**
     * List all tasks belonging to a specific organization.
     */
    public function byOrganization($organizationId)
    {
        $tasks = Task::with(['call', 'organization'])
            ->where('organization_id', $organizationId)
            ->get();

        return response()->json($tasks);
    }

    /**
     * List all published Program B tasks visible to the public.
     */
    public function publicTasks()
    {
        $tasks = Task::with(['call', 'organization'])
            ->where('status', 'published')
            ->whereHas('call', function ($query) {
                $query->where('program', 'b');
            })
            ->get();

        return response()->json($tasks);
    }

    /**
     * List tasks belonging to the authenticated user's organization.
     */
    public function index(Request $request)
    {
        $tasks = $this->taskService->index($request->user()->id);

        return response()->json($tasks);
    }

    /**
     * Show a single task with all relations.
     */
    public function show(Request $request, $id)
    {
        $task = $this->taskService->findWithRelations($id);

        $this->authorize('view', $task);

        return response()->json($task);
    }

    /**
     * Create a standalone task.
     */
    public function store(StoreTaskRequest $request)
    {
        $this->authorize('create', Task::class);

        $task = $this->taskService->create($request->validated(), $request->user());

        return response()->json($task, 201);
    }

    /**
     * Update a standalone task.
     */
    public function update(UpdateTaskRequest $request, $id)
    {
        $task = Task::findOrFail($id);

        $this->authorize('update', $task);

        $task = $this->taskService->update($id, $request->validated());

        return response()->json($task);
    }

    /**
     * Delete a task permanently.
     */
    public function destroy(Request $request, $id)
    {
        $task = Task::findOrFail($id);

        $this->authorize('delete', $task);

        $this->taskService->delete($id);

        return response()->json(['message' => 'Task deleted successfully']);
    }

}
