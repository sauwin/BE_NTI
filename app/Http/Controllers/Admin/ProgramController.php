<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Program;
use Illuminate\Http\Request;

/**
 * @tags Admin Management
 * Endpoints for managing program lifecycle definitions, including creating, updating, and retrieving configuration settings for various program types (e.g., grants vs. live practice).
 */
class ProgramController extends Controller
{
    /**
     * Select all calls
     */
    public function index()
    {
        return response()->json(Program::all());
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'code' => 'required|in:program_a,program_b|unique:programs,code',
            'type' => 'required|in:grant,live_practice',
            'is_active' => 'boolean',
            'config' => 'nullable|array'
        ]);

        $program = Program::create($validated);
        return response()->json($program, 201);
    }

    /**
     * Show the call for
     */
    public function show(Program $program)
    {
        return response()->json($program);
    }

    /**
     * Update the call
     */
    public function update(Request $request, Program $program)
    {
        $validated = $request->validate([
            'type' => 'sometimes|required|in:grant,live_practice',
            'is_active' => 'boolean',
            'config' => 'nullable|array'
        ]);

        $program->update($validated);
        return response()->json($program);
    }

    /**
     * Delete the call
     */
    public function destroy(Program $program)
    {
        $program->delete();
        return response()->json(null, 204);
    }
}