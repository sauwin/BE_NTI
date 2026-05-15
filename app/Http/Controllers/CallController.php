<?php

namespace App\Http\Controllers;

use App\Models\Call;
use App\Models\Program;
use Illuminate\Http\Request;

class CallController extends Controller
{
    // Public — get active call for a program
    public function active(string $program_type)
    {
        $call = Call::whereHas('program', fn ($q) => $q->where('code', 'program_'.$program_type))
            ->where('status', 'open')
            ->latest()
            ->first();

        if (! $call) {
            return response()->json(['message' => 'No active call found'], 404);
        }

        return response()->json($call);
    }

    // Admin — list all calls
    public function index(Request $request)
    {
        $calls = Call::with('program')
            ->orderByDesc('created_at')
            ->get();

        return response()->json($calls);
    }

    // Admin — create call
    public function store(Request $request)
    {
        $data = $request->validate([
            'program_type' => 'required|in:a,b',
            'opens_at' => 'required|date',
            'deadline_at' => 'required|date|after:opens_at',
            'min_team_size' => 'integer|min:1',
            'max_team_size' => 'nullable|integer|min:1',
            'evaluation_criteria' => 'nullable|array',
            'required_documents' => 'nullable|array',
        ]);

        $program = Program::where('code', 'program_'.$data['program_type'])->firstOrFail();

        $call = Call::create([
            'program_id' => $program->id,
            'status' => 'draft',
            'opens_at' => $data['opens_at'],
            'deadline_at' => $data['deadline_at'],
            'min_team_size' => $data['min_team_size'] ?? 3,
            'max_team_size' => $data['max_team_size'] ?? null,
            'evaluation_criteria' => $data['evaluation_criteria'] ?? [],
            'required_documents' => $data['required_documents'] ?? [],
            'created_by' => $request->user()->id,
        ]);

        return response()->json($call, 201);
    }

    // Admin — update call
    public function update(Request $request, int $id)
    {
        $call = Call::findOrFail($id);

        $data = $request->validate([
            'status' => 'sometimes|in:draft,open,closed,archived',
            'opens_at' => 'sometimes|date',
            'deadline_at' => 'sometimes|date',
            'min_team_size' => 'sometimes|integer|min:1',
            'max_team_size' => 'nullable|integer|min:1',
            'evaluation_criteria' => 'nullable|array',
            'required_documents' => 'nullable|array',
        ]);

        $call->update($data);

        return response()->json($call);
    }

    // Admin — delete draft call
    public function destroy(int $id)
    {
        $call = Call::findOrFail($id);

        if ($call->status !== 'draft') {
            return response()->json(['message' => 'Only draft calls can be deleted.'], 422);
        }

        $call->delete();

        return response()->json(['message' => 'Call deleted']);
    }

    // Admin — show single call
    public function show(int $id)
    {
        $call = Call::with('program')->findOrFail($id);

        return response()->json($call);
    }
}
