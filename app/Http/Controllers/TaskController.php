<?php

namespace App\Http\Controllers;

use App\Models\Call;
use App\Models\Task;
use Illuminate\Http\Request;

class TaskController extends Controller
{
    public function byOrganization($organizationId)
    {
        $tasks = Task::with(['call.program', 'organization'])
            ->where('organization_id', $organizationId)
            ->get();

        return response()->json($tasks);
    }

    public function publicTasks()
    {
        $tasks = Task::with(['call.program', 'organization'])
            ->where('status', 'published')
            ->whereHas('call.program', function ($query) {
                $query->where('code', 'program_b');
            })
            ->get();

        return response()->json($tasks);
    }

    public function index(Request $request)
    {
        $tasks = Task::with(['call.program', 'organization'])
            ->where('product_owner_user_id', $request->user()->id)
            ->get();

        return response()->json($tasks);
    }

    public function show($id)
    {
        $task = Task::with(['organization', 'call', 'documents'])->find($id);

        if (!$task) {
            return response()->json(['message' => 'Task not found'], 404);
        }

        return response()->json($task);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'call_id' => 'required|integer|exists:calls,id',
            'title' => 'required|string|max:255',
            'budget' => 'nullable|numeric|min:0',
            'brief' => 'nullable|string|max:2000',
            'status' => 'nullable|in:draft,published',
            'short_description' => 'nullable|string|max:500',
            'project_goal' => 'nullable|string',
            'expected_outcome' => 'nullable|string',
            'detailed_technical_description' => 'nullable|string',
            'required_technologies' => 'nullable|array',
            'architecture_requirements' => 'nullable|string',
            'integrations_apis' => 'nullable|string',
            'platforms' => 'nullable|string',
            'required_skills' => 'nullable|array',
            'preferred_team_size' => 'nullable|integer',
            'required_experience' => 'nullable|string',
            'expected_duration' => 'nullable|string',
            'milestones' => 'nullable|string',
            'deadline' => 'nullable|date',
        ]);

        $organizationId = $request->user()->organization_id;

        if (! $organizationId) {
            return response()->json(['message' => 'You must belong to an organization.'], 403);
        }

        $call = Call::with('program')->findOrFail($data['call_id']);
        if ($call->program->code !== 'program_b') {
            return response()->json(['message' => 'Only Program B calls can receive company tasks.'], 422);
        }

        $task = Task::create(array_merge($data, [
            'organization_id' => $organizationId,
            'product_owner_user_id' => $request->user()->id,
            'status' => $data['status'] ?? 'draft',
        ]));

        return response()->json($task, 201);
    }

    public function update(Request $request, $id)
    {
        $task = Task::findOrFail($id);
        if ($task->product_owner_user_id !== $request->user()->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $data = $request->validate([
            'title' => 'sometimes|required|string|max:255',
            'brief' => 'nullable|string|max:2000',
            'budget' => 'nullable|numeric|min:0',
            'status' => 'sometimes|required|in:draft,published,in_matching,assigned,in_progress,closed',
            'short_description' => 'nullable|string|max:500',
            'project_goal' => 'nullable|string',
            'expected_outcome' => 'nullable|string',
            'detailed_technical_description' => 'nullable|string',
            'required_technologies' => 'nullable|array',
            'architecture_requirements' => 'nullable|string',
            'integrations_apis' => 'nullable|string',
            'platforms' => 'nullable|string',
            'required_skills' => 'nullable|array',
            'preferred_team_size' => 'nullable|integer',
            'required_experience' => 'nullable|string',
            'expected_duration' => 'nullable|string',
            'milestones' => 'nullable|string',
            'deadline' => 'nullable|date',
        ]);

        $task->update($data);

        return response()->json($task);
    }

    public function destroy(Request $request, $id)
    {
        $task = Task::findOrFail($id);

        if ($task->product_owner_user_id !== $request->user()->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $task->delete();
        return response()->json(['message' => 'Task deleted successfully']);
    }
}
