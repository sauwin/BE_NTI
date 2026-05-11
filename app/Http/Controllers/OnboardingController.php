<?php

namespace App\Http\Controllers;

use App\Models\StudentProfile;
use App\Models\StudentSkill;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class OnboardingController extends Controller
{
    public function status(Request $request)
    {
        $profile = StudentProfile::where('user_id', $request->user()->id)->first();

        return response()->json([
            'completed' => $profile !== null,
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'university'                    => 'required|string|max:255',
            'study_program'                 => 'required|string|max:255',
            'year_of_study'                 => 'required|integer|min:1|max:6',
            'bio'                           => 'nullable|string|max:1000',
            'github_url'                    => 'nullable|url|max:255',
            'skills'                        => 'nullable|array',
            'skills.*.skill'                => 'required_with:skills|string|max:100',
            'skills.*.level'                => 'required_with:skills|in:beginner,intermediate,advanced',
            'gdpr_consent'                  => 'required|accepted',
            'academic_declaration_confirmed'=> 'required|accepted',
        ]);

        DB::transaction(function () use ($request) {
            $profile = StudentProfile::updateOrCreate(
                ['user_id' => $request->user()->id],
                [
                    'university'                     => $request->university,
                    'study_program'                  => $request->study_program,
                    'year_of_study'                  => $request->year_of_study,
                    'bio'                            => $request->bio,
                    'github_url'                     => $request->github_url,
                    'academic_declaration_confirmed' => true,
                ]
            );

            // Replace skills
            $profile->skills()->delete();

            if ($request->has('skills')) {
                foreach ($request->skills as $s) {
                    StudentSkill::create([
                        'student_profile_id' => $profile->id,
                        'skill'              => $s['skill'],
                        'level'              => $s['level'],
                    ]);
                }
            }
        });

        return response()->json(['message' => 'Onboarding completed'], 201);
    }
}