<?php

namespace App\Http\Controllers;

use App\Models\Call;
use App\Models\Program;
use App\Services\AuditService;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use App\Models\Application;
use Illuminate\Support\Facades\DB;

class CallController extends Controller
{
    public function active(Request $request, ?string $program_type = null)
    {
        $query = Call::with('program')
            ->where('status', 'open');

        if ($program_type) {
            $program_type = strtolower(trim($program_type));

            $query->whereHas('program', fn ($q) =>
                $q->where('code', 'program_' . $program_type)
            );
        }

        $calls = $query
            ->orderBy('deadline_at')
            ->first();

        return response()->json($calls);
    }

    public function index(Request $request)
    {
        $calls = Call::with('program')
            ->orderByDesc('created_at')
            ->get();

        return response()->json($calls);
    }

    public function store(Request $request)
    {
        if ($request->has('program_id')) {
            $rawProgram = $request->input('program_id');
            $type = str_contains($rawProgram, 'program_a') ? 'a' : (str_contains($rawProgram, 'program_b') ? 'b' : null);
            if ($type) {
                $request->merge(['program_type' => $type]);
            }
        }

        try {
            $data = $request->validate([
                'program_type' => 'required|in:a,b',
                'title' => 'nullable|string|max:255',
                'name' => 'nullable|string|max:255',
                'status' => 'sometimes|required|in:draft,open,closed,archived',
                'opens_at' => 'nullable|date',
                'deadline_at' => 'nullable|date',
                'min_team_size' => 'nullable|integer|min:1',
                'max_team_size' => 'nullable|integer',
                'evaluation_criteria' => 'nullable',
                'required_documents' => 'nullable',
                'form_config' => 'nullable',
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'error' => 'Validation Failed',
                'messages' => $e->errors(),
                'received_data' => $request->all(),
            ], 422);
        }

        $program = Program::where('code', 'program_'.$data['program_type'])->first();
        if (! $program) {
            return response()->json(['error' => 'Program not found for type: '.$data['program_type']], 422);
        }

        $documents = $request->input('form_config') ?? $request->input('required_documents') ?? [];
        if (is_string($documents)) {
            $documents = json_decode($documents, true) ?? [];
        }

        $callName = $request->input('title') ?? $request->input('name') ?? 'Bez názvu';

        $call = Call::create([
            'program_id' => $program->id,
            'name' => $callName,
            'status' => $request->input('status') ?? 'draft',
            'opens_at' => $request->input('opens_at') ? now()->parse($request->input('opens_at')) : null,
            'deadline_at' => $request->input('deadline_at') ? now()->parse($request->input('deadline_at')) : null,
            'min_team_size' => $request->input('min_team_size') ?? 1,
            'max_team_size' => $request->input('max_team_size') ?? null,
            'evaluation_criteria' => $request->input('evaluation_criteria') ?? [],
            'required_documents' => $documents,
            'created_by' => $request->user()->id ?? 1,
        ]);

        $call->label = $callName;

        AuditService::log('create_call', 'call', [
            'call_id' => $call->id,
            'program_type' => $call->program_type,
            'name' => $callName,
        ]);

        return response()->json($call, 201);
    }

    public function update(Request $request, int $id)
    {
        $call = Call::findOrFail($id);

        $data = $request->validate([
            'title' => 'sometimes|string|max:255',
            'program_type' => 'sometimes|in:a,b',
            'status' => 'sometimes|in:draft,open,closed,archived',
            'opens_at' => 'nullable|date',
            'deadline_at' => 'nullable|date|after_or_equal:opens_at',
            'min_team_size' => 'sometimes|integer|min:1',
            'max_team_size' => 'nullable|integer|min:1',
            'evaluation_criteria' => 'nullable|array',
            'form_config' => 'nullable|string',
            'required_documents' => 'nullable|array',
        ]);

        if (isset($data['program_type'])) {
            $program = Program::where('code', 'program_'.$data['program_type'])->first();
            if ($program) {
                $call->program_id = $program->id;
            }
        }

        if (isset($data['title'])) {
            $call->name = $data['title'];
        }

        if (isset($data['max_team_size']) && $data['max_team_size'] !== null) {
            $min = $data['min_team_size'] ?? $call->min_team_size;
            if ($data['max_team_size'] < $min) {
                return response()->json(['message' => 'Max team size cannot be less than min team size.'], 422);
            }
            $call->max_team_size = $data['max_team_size'];
        } elseif (array_key_exists('max_team_size', $data)) {
            $call->max_team_size = null;
        }

        $documents = $data['form_config'] ?? null;
        if (is_string($documents)) {
            $documents = json_decode($documents, true) ?? [];
        } elseif (isset($data['required_documents'])) {
            $documents = $data['required_documents'];
        }

        if ($documents !== null) {
            $call->required_documents = $documents;
        }

        if (isset($data['min_team_size'])) {
            $call->min_team_size = $data['min_team_size'];
        }
        if (isset($data['status'])) {
            $call->status = $data['status'];
        }
        if (array_key_exists('opens_at', $data)) {
            $call->opens_at = $data['opens_at'] ? now()->parse($data['opens_at']) : null;
        }
        if (array_key_exists('deadline_at', $data)) {
            $call->deadline_at = $data['deadline_at'] ? now()->parse($data['deadline_at']) : null;
        }

        $call->save();

        return response()->json($call);
    }

    public function destroy(int $id)
    {
        $call = Call::findOrFail($id);

        if ($call->status !== 'draft') {
            return response()->json(['message' => 'Only draft calls can be deleted.'], 422);
        }

        $callId = $call->id;
        $call->delete();

        AuditService::log('delete_call', 'call', [
            'call_id' => $callId,
        ]);

        return response()->json(['message' => 'Call deleted']);
    }

    public function show(int $id)
    {
        $call = Call::with('program')->findOrFail($id);

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
            $result = DB::transaction(function () use ($call, $validated) {
                $applicationsToMove = Application::where('call_id', $call->id)
                    ->where('status', 'formally_verified')
                    ->count();
                $call->update([
                    'evaluation_scheduled_at' => $validated['evaluation_scheduled_at'],
                ]);
                
                if ($applicationsToMove > 0) {
                    Application::where('call_id', $call->id)
                        ->where('status', 'formally_verified')
                        ->update([
                            'status' => 'under_evaluation',
                            'updated_at' => now(),
                        ]);
                }
                
                return [
                    'call_id' => $call->id,
                    'evaluation_scheduled_at' => $call->evaluation_scheduled_at,
                    'applications_moved' => $applicationsToMove,
                ];
            });
            
            return response()->json([
                'message' => 'Evaluation scheduled successfully',
                'data' => [
                    'call_id' => $result['call_id'],
                    'evaluation_scheduled_at' => $result['evaluation_scheduled_at'],
                    'applications_moved' => $result['applications_moved'],
                ],
            ], 200);
            
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error scheduling evaluation',
                'error' => $e->getMessage(),
            ], 500);
        }
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
