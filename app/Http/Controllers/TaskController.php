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
                NotificationController::log($admin->id, $admin->email, 'task_created', 'New Program B task created: '.$call->title, ['task_id' => $task->id, 'call_id' => $call->id]);
            }
            if ($task->organization) {
                foreach ($task->organization->members as $member) {
                    NotificationController::log($member->id, $member->email, 'task_created', 'Your task "'.$call->title.'" has been successfully created.', ['task_id' => $task->id]);
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

    public function byOrganization($organizationId)
    {
        $tasks = Task::with(['call', 'organization'])
            ->where('organization_id', $organizationId)
            ->get();

        return response()->json($tasks);
    }

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

    public function index(Request $request)
    {
        $tasks = $this->taskService->index($request->user()->id);

        return response()->json($tasks);
    }

    public function show(Request $request, $id)
    {
        $task = $this->taskService->findWithRelations($id);

        $this->authorize('view', $task);

        return response()->json($task);
    }

    public function store(StoreTaskRequest $request)
    {
        $this->authorize('create', Task::class);

        $task = $this->taskService->create($request->validated(), $request->user());

        return response()->json($task, 201);
    }

    public function update(UpdateTaskRequest $request, $id)
    {
        $task = Task::findOrFail($id);

        $this->authorize('update', $task);

        $task = $this->taskService->update($id, $request->validated());

        return response()->json($task);
    }

    public function destroy(Request $request, $id)
    {
        $task = Task::findOrFail($id);

        $this->authorize('delete', $task);

        $this->taskService->delete($id);

        return response()->json(['message' => 'Task deleted successfully']);
    }

    /**
     * List all Program B tasks with optional ?status= filter.
     */
    public function adminIndex(Request $request)
    {
        $request->validate([
            'status' => 'nullable|in:published,in_matching,assigned,in_progress,closed',
            'per_page' => 'nullable|integer|min:1|max:100',
        ]);

        $tasks = $this->taskService->adminTasks(
            $request->input('status'),
            (int) $request->input('per_page', 20)
        );

        return response()->json($tasks);
    }

    /**
     * Move task to the next status in the chain:
     */
    public function adminAdvanceStatus(Request $request, int $id)
    {
        $request->validate([
            'product_owner_user_id' => 'nullable|integer|exists:users,id',
        ]);

        $task = Task::findOrFail($id);

        try {
            $task = $this->taskService->advanceStatus(
                $task,
                $request->input('product_owner_user_id')
            );
        } catch (\DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        AuditService::log('advance_status', 'Task', [
            'task_id' => $task->id,
            'new_status' => $task->status,
        ]);

        if ($task->status === 'assigned') {
            $admins = User::whereHas('roles', fn ($q) => $q->whereIn('slug', ['nti_admin', 'super_admin']))->get();
            foreach ($admins as $admin) {
                NotificationController::log($admin->id, $admin->email, 'task_assigned', 'Task "'.$task->title.'" has been assigned to a team.', ['task_id' => $task->id]);
            }
            if ($task->productOwner) {
                NotificationController::log($task->productOwner->id, $task->productOwner->email, 'task_assigned', 'Your task "'.$task->title.'" has been assigned to a team.', ['task_id' => $task->id]);
            }
            $applications = Application::where('call_id', $task->call_id)->where('program_type', 'b')->with('team.members')->get();
            foreach ($applications as $app) {
                foreach ($app->team?->members ?? [] as $member) {
                    NotificationController::log($member->id, $member->email, 'added_to_program_b', 'You have been added to Program B task: '.$task->title, ['task_id' => $task->id]);
                }
            }
        }

        return response()->json([
            'message' => 'Task status advanced.',
            'task' => $task,
        ]);
    }

    /**
     * Set an explicit admin-managed status (for overrides / corrections).
     */
    public function adminSetStatus(Request $request, int $id)
    {
        $request->validate([
            'status' => 'required|in:published,in_matching,assigned,in_progress,closed',
            'product_owner_user_id' => 'nullable|integer|exists:users,id',
        ]);

        $task = Task::findOrFail($id);

        try {
            $task = $this->taskService->setAdminStatus(
                $task,
                $request->input('status'),
                $request->input('product_owner_user_id')
            );
        } catch (\DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        AuditService::log('set_status', 'Task', [
            'task_id' => $task->id,
            'new_status' => $task->status,
        ]);
        if ($task->productOwner) {
            NotificationController::log($task->productOwner->id, $task->productOwner->email, 'task_status_set', 'Task "'.$task->title.'" status set to '.$task->status, ['task_id' => $task->id]);
        }

        return response()->json([
            'message' => 'Task status updated.',
            'task' => $task,
        ]);
    }
}
