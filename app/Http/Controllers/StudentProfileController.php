<?php

namespace App\Http\Controllers;

use App\Models\StudentProfile;
use Illuminate\Http\Request;

class StudentProfileController extends Controller
{
    public function store(Request $request)
    {
        $data = $request->validate([
            'study_program' => 'required|string|max:255',
            'year_of_study' => 'required|integer|min:1|max:6',
            'university' => 'nullable|string|max:255',
            'bio' => 'nullable|string',
            'github_url' => 'nullable|url',
            'academic_declaration_confirmed' => 'required|boolean',
        ]);

        $profile = StudentProfile::updateOrCreate(
            ['user_id' => $request->user()->id],
            $data
        );

        return response()->json($profile, 201);
    }

    public function show(Request $request)
    {
        $profile = StudentProfile::where('user_id', $request->user()->id)->first();

        return response()->json($profile);
    }
}
