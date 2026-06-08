<?php

namespace App\Http\Controllers;

use App\Models\Call;
use App\Models\Program;
use App\Services\AuditService;
use Illuminate\Http\Request;
use App\Models\Application;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use App\Http\Requests\StoreCallRequest;
use App\Http\Requests\UpdateCallRequest;
use App\Services\CallService;

/**
 * @tags Call Management
 * Endpoints for configuring program calls, tracking active submission windows, managing form configurations, and scheduling application evaluation phases.
 */
class CallController extends Controller
{
    protected $callService;

    public function __construct(CallService $callService)
    {
        $this->callService = $callService;
    }
    public function active(Request $request, ?string $program_type = null)
    {
        $query = Call::where('status', 'open');

        if ($program_type) {
            $program_type = strtolower(trim($program_type));
            $query->where('program', $program_type);
        }

        $call = $query
            ->orderBy('deadline_at')
            ->first();

        return response()->json($call);
    }

    public function index(Request $request)
    {
        $calls = Call::orderByDesc('created_at')->get();

        return response()->json($calls);
    }

    public function store(StoreCallRequest $request)
    {
        $call = $this->callService->create($request->validated(), $request->user()->id ?? 1);

        return response()->json($call, 201);
    }

    public function update(UpdateCallRequest $request, int $id)
    {
        try {
            $call = $this->callService->update($id, $request->validated());
        } catch (\InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json($call);
    }

    public function destroy(int $id)
    {
        try {
            $this->callService->delete($id);
        } catch (\DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json(['message' => 'Call deleted']);
    }

    public function show(int $id)
    {
        $call = Call::findOrFail($id);

        return response()->json($call);
    }

    public function updateStatus(Request $request, int $id)
    {
        $request->validate([
            'status' => 'required|in:draft,open,closed,archived',
        ]);

        $call = Call::findOrFail($id);
        $call->status = $request->status;
        $call->save();

        return response()->json([
            'message' => 'Call status updated successfully',
            'call' => $call,
        ]);
    }

    public function scheduleEvaluation(Request $request, int $id)
    {
        $call = Call::findOrFail($id);
        $this->authorize('update', $call);
        
        $validated = $request->validate([
            'evaluation_scheduled_at' => 'required|date_format:Y-m-d\TH:i|after:now',
        ], [
            'evaluation_scheduled_at.required' => 'Evaluation date is required',
            'evaluation_scheduled_at.date_format' => 'Invalid date format',
            'evaluation_scheduled_at.after' => 'Date must be in the future',
        ]);
        
        try {
            $call->update([
                'evaluation_scheduled_at' => $validated['evaluation_scheduled_at'],
            ]);
            
            return response()->json([
                'message' => 'Evaluation scheduled successfully',
                'data' => [
                    'call_id' => $call->id,
                    'evaluation_scheduled_at' => $call->evaluation_scheduled_at,
                ],
            ], 200);
            
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error scheduling evaluation',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function moveApplicationsUnderEvaluation(Call $call) {

        $this->authorize('update', $call);
            
        $movedAppsCount = $call->startEvaluation();

        return response()->json(['applications_moved' => $movedAppsCount], 200);
    }

    public function getEvaluationInfo(int $id)
    {
        $call = Call::findOrFail($id);
        
        $applicationsStats = [
            'formally_verified' => Application::where('call_id', $id)
                ->where('status', 'formally_verified')
                ->count(),
            'under_evaluation' => Application::where('call_id', $id)
                ->where('status', 'under_evaluation')
                ->count(),
            'total' => Application::where('call_id', $id)->count(),
        ];
        
        return response()->json([
            'call_id' => $call->id,
            'evaluation_scheduled_at' => $call->evaluation_scheduled_at,
            'applications' => $applicationsStats,
        ]);
    }
}
