<?php

namespace App\Http\Controllers;

use App\Models\Application;
use Illuminate\Http\Request;

class ApplicationController extends Controller
{
    public function store(Request $request)
    {
        $data = $request->validate([
            'call_id'       => 'required', // ТИМЧАСОВО required|exists:calls,id'
            'applicant_type'=> 'required|in:student,team',
            'program_type'  => 'required|in:a,b',
            'team_id'       => 'nullable|exists:teams,id',
        ]);

        $application = Application::create([
            'call_id'            => $data['call_id'],
            'applicant_type'     => $data['applicant_type'],
            'program_type'       => $data['program_type'],
            'team_id'            => $data['team_id'] ?? null,
            'student_profile_id' => null,
            'status'             => 'draft',
        ]);

        return response()->json([
            'application_id' => $application->id,
        ], 201);
    }
}