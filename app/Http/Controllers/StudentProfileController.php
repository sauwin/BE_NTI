<?php

namespace App\Http\Controllers;

use App\Models\Role;
use App\Models\StudentProfile;
use App\Models\StudentSkill;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * @tags User Management
 * Endpoints for managing student academic profiles, including tracking university information, study progress, technical skillsets, and conditional assignment of student roles within the system.
 */
class StudentProfileController extends Controller
{
    /**
     * Create new student profile
     */
    public function store(Request $request)
    {
        $this->authorize('create', StudentProfile::class);

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

        $user = $request->user();
        $studentRole = Role::where('slug', 'student')->first();
        $existingRole = DB::table('user_roles')->where('user_id', $user->id)->first();
        if ($studentRole && ! $existingRole) {
            DB::table('user_roles')->insert([
                'user_id' => $user->id,
                'role_id' => $studentRole->id,
                'granted_by' => null,
                'granted_at' => now(),
            ]);
        }

        return response()->json($profile, 201);
    }

    /**
     * Show student profile
     */
    public function show(Request $request)
    {
        $profile = StudentProfile::with('skills')
            ->where('user_id', $request->user()->id)
            ->firstOrFail();

        $this->authorize('view', $profile);

        return response()->json($profile);
    }

    /**
     * Update student profile
     */
    public function update(Request $request)
    {
        $request->validate([
            'university' => 'required|string|max:255',
            'study_program' => 'required|string|max:255',
            'year_of_study' => 'required|integer|min:1|max:6',
            'bio' => 'nullable|string|max:1000',
            'github_url' => 'nullable|url|max:255',
            'academic_declaration_confirmed' => 'nullable|boolean',
            'skills' => 'nullable|array',
            'skills.*.skill' => 'required_with:skills|string|max:100',
            'skills.*.level' => 'required_with:skills|in:beginner,intermediate,advanced',
        ]);

        $profile = StudentProfile::firstOrNew(['user_id' => $request->user()->id]);

        $this->authorize('update', $profile);

        DB::transaction(function () use ($request, $profile) {
            $profile->fill([
                'university' => $request->university,
                'study_program' => $request->study_program,
                'year_of_study' => $request->year_of_study,
                'bio' => $request->bio,
                'github_url' => $request->github_url,
                'academic_declaration_confirmed' => $request->academic_declaration_confirmed ?? false,
            ])->save();

            $profile->skills()->delete();

            foreach ($request->skills ?? [] as $s) {
                StudentSkill::create([
                    'student_profile_id' => $profile->id,
                    'skill' => $s['skill'],
                    'level' => $s['level'],
                ]);
            }

            $user = $request->user();
            $studentRole = Role::where('slug', 'student')->first();
            $existingRole = DB::table('user_roles')->where('user_id', $user->id)->first();
            if ($studentRole && ! $existingRole) {
                DB::table('user_roles')->insert([
                    'user_id' => $user->id,
                    'role_id' => $studentRole->id,
                    'granted_by' => null,
                    'granted_at' => now(),
                ]);
            }
        });

        return response()->json(['message' => 'Profile updated']);
    }
}