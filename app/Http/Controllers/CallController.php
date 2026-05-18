<?php

namespace App\Http\Controllers;

use App\Models\Call;
use App\Models\Program;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CallController extends Controller
{
    public function active(Request $request, string $program_type = null)
    {
        if (!$program_type) {
            $program_type = $request->query('program', 'b'); 
        }

        $program_type = strtolower(trim($program_type));

        $call = Call::whereHas('program', fn ($q) => $q->where('code', 'program_' . $program_type))
            ->where('status', 'open')
            ->latest()
            ->first();

        if (! $call) {
            return response()->json([]);
        }

        $lang = $request->query('lang', 'sk');
        $translation = DB::table('call_translations')
            ->where('call_id', $call->id)
            ->where('language', $lang)
            ->first();

        $call->name = $translation ? $translation->name : 'Letný semester 2026';
        $call->label = $translation ? $translation->name : 'Letný semester 2026';
        return response()->json([$call]);
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
        $data = $request->validate([
            'program_type' => 'required|in:a,b',
            'status' => 'sometimes|required|in:draft,open,closed,archived',
            'opens_at' => 'nullable|date', 
            'deadline_at' => 'nullable|date|after_or_equal:opens_at',
            'min_team_size' => 'integer|min:1',
            'max_team_size' => 'nullable|integer|gte:min_team_size', 
            'evaluation_criteria' => 'nullable|array',
            'required_documents' => 'nullable|array', 
        ]);

        $program = Program::where('code', 'program_'.$data['program_type'])->firstOrFail();

        $call = Call::create([
            'program_id' => $program->id,
            'status' => $data['status'] ?? 'draft',
            'opens_at' => $data['opens_at'] ?? null,
            'deadline_at' => $data['deadline_at'] ?? null,
            'min_team_size' => $data['min_team_size'] ?? 3,
            'max_team_size' => $data['max_team_size'] ?? null,
            'evaluation_criteria' => $data['evaluation_criteria'] ?? [],
            'required_documents' => $data['required_documents'] ?? [],
            'created_by' => $request->user()->id, 
        ]);

        return response()->json($call, 201);
    }

    // Admin — update call (КРАЩЕ: покращена валідація лімітів команд)
    public function update(Request $request, int $id)
    {
        $call = Call::findOrFail($id);

        $data = $request->validate([
            'status' => 'sometimes|in:draft,open,closed,archived',
            'opens_at' => 'sometimes|date',
            'deadline_at' => 'sometimes|date|after_or_equal:opens_at',
            'min_team_size' => 'sometimes|integer|min:1',
            'max_team_size' => 'nullable|integer|min:1',
            'evaluation_criteria' => 'nullable|array',
            'required_documents' => 'nullable|array',
        ]);

        if (isset($data['max_team_size']) && $data['max_team_size'] !== null) {
            $min = $data['min_team_size'] ?? $call->min_team_size;
            if ($data['max_team_size'] < $min) {
                return response()->json(['message' => 'Max team size cannot be less than min team size.'], 422);
            }
        }

        $call->update($data);

        return response()->json($call);
    }

    public function destroy(int $id)
    {
        $call = Call::findOrFail($id);

        if ($call->status !== 'draft') {
            return response()->json(['message' => 'Only draft calls can be deleted.'], 422);
        }

        $call->delete();

        return response()->json(['message' => 'Call deleted']);
    }

    public function show(int $id)
    {
        $call = Call::with('program')->findOrFail($id);

        return response()->json($call);
    }
}