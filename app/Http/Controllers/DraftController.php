<?php

namespace App\Http\Controllers;

use App\Models\Draft;
use Illuminate\Http\Request;

/**
 * @tags Application Management
 * Endpoints for auto-saving, updating, and retrieving volatile application progress drafts associated with specific program types.
 */
class DraftController extends Controller
{
    /**
     * Create and update draft
     */
    public function store(Request $request)
    {
        $data = $request->validate([
            'program_type' => 'required',
        ]);

        $draft = Draft::updateOrCreate([
            'user_id' => $request->user()->id,
            'program_type' => $data['program_type'],
        ],
        [
            'data' => $request->input('data', []),
        ]);

        return response()->json($draft);
    }

    /**
     * Select last draft 
     */
    public function show(Request $request, string $program_type)
    {
        $draft = Draft::where('user_id', $request->user()->id)->where('program_type', $program_type)->first();

        return response()->json($draft);
    }
}
