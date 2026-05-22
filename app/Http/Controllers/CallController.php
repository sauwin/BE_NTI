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
            $program_type = $request->query('program', 'a'); 
        }

        $program_type = strtolower(trim($program_type));

        $call = Call::whereHas('program', fn ($q) => $q->where('code', 'program_' . $program_type))
            ->where('status', 'open')
            ->latest()
            ->first();

        if (!$call) {
            return response()->json(null);
        }

        $lang = $request->query('lang', 'sk');
        $translation = DB::table('call_translations')
            ->where('call_id', $call->id)
            ->where('language', $lang)
            ->first();

        $callName = $translation ? $translation->name : 'Výzva #' . $call->id;
        $call->name = $callName;
        $call->label = $callName;

        return response()->json($call);
    }

    public function index(Request $request)
    {
        $lang = $request->query('lang', 'sk');

        $calls = Call::select('calls.*', 'call_translations.name as name')
            ->leftJoin('call_translations', function ($join) use ($lang) {
                $join->on('calls.id', '=', 'call_translations.call_id')
                     ->where('call_translations.language', '=', $lang);
            })
            ->with('program')
            ->orderByDesc('calls.created_at')
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
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'error' => 'Validation Failed',
                'messages' => $e->errors(),
                'received_data' => $request->all()
            ], 422);
        }

        $program = Program::where('code', 'program_' . $data['program_type'])->first();
        if (!$program) {
            return response()->json(['error' => 'Program not found for type: ' . $data['program_type']], 422);
        }
        
        $documents = $request->input('form_config') ?? $request->input('required_documents') ?? [];
        if (is_string($documents)) {
            $documents = json_decode($documents, true) ?? [];
        }

        $callName = $request->input('title') ?? $request->input('name') ?? 'Bez názvu';

        $call = DB::transaction(function () use ($program, $request, $documents, $callName) {
            $newCall = Call::create([
                'program_id' => $program->id,
                'status' => $request->input('status') ?? 'draft',
                'opens_at' => $request->input('opens_at') ? now()->parse($request->input('opens_at')) : null,
                'deadline_at' => $request->input('deadline_at') ? now()->parse($request->input('deadline_at')) : null,
                'min_team_size' => $request->input('min_team_size') ?? 1,
                'max_team_size' => $request->input('max_team_size') ?? null,
                'evaluation_criteria' => $request->input('evaluation_criteria') ?? [],
                'required_documents' => $documents,
                'created_by' => $request->user()->id ?? 1, 
            ]);

            DB::table('call_translations')->insert([
                [
                    'call_id' => $newCall->id,
                    'language' => 'sk',
                    'name' => $callName,
                    'description' => 'Vytvorené cez central administráciu',
                    'created_at' => now(),
                    'updated_at' => now()
                ],
                [
                    'call_id' => $newCall->id,
                    'language' => 'en',
                    'name' => $callName . ' (EN)',
                    'description' => 'Created via central administration',
                    'created_at' => now(),
                    'updated_at' => now()
                ]
            ]);

            return $newCall;
        });

        $call->name = $callName;
        $call->label = $callName;

        return response()->json($call, 201);
    }

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
            'form_config' => 'nullable|array',
        ]);

        if (isset($data['form_config'])) {
            $data['required_documents'] = $data['form_config'];
            unset($data['form_config']);
        }

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

        DB::table('call_translations')->where('call_id', $id)->delete();
        $call->delete();

        return response()->json(['message' => 'Call deleted']);
    }

    public function show(int $id)
    {
        $lang = request()->query('lang', 'sk');

        $call = Call::select('calls.*', 'call_translations.name as name', 'call_translations.description as description')
            ->leftJoin('call_translations', function ($join) use ($lang) {
                $join->on('calls.id', '=', 'call_translations.call_id')
                     ->where('call_translations.language', '=', $lang);
            })
            ->with('program')
            ->findOrFail($id);

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
            'call' => $call
        ]);
    }
}