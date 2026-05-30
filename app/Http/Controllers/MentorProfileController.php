<?php

namespace App\Http\Controllers;

use App\Models\MentorProfile;
use Illuminate\Http\Request;

class MentorProfileController extends Controller
{
    /**
     * Show profile for mentors
     */
    public function show(Request $request)
    {
        $profile = MentorProfile::where('user_id', $request->user()->id)->first();
        return response()->json($profile);
    }

    /**
     * Update or create profile for mentors
     */
    public function update(Request $request)
    {
        $request->validate([
            'bio' => 'nullable|string|max:1000',
            'expertise_areas' => 'nullable|array',
            'expertise_areas.*' => 'string|max:100',
            'available' => 'nullable|boolean',
        ]);

        $profile = MentorProfile::updateOrCreate(
            ['user_id' => $request->user()->id],
            [
                'bio' => $request->bio,
                'expertise_areas' => $request->expertise_areas ?? [],
                'available' => $request->available ?? true,
            ]
        );

        return response()->json($profile);
    }
}