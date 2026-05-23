<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

use App\Models\Team;
use App\Models\User;

class TeamController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $teams = $request->user()
        ->teams()
        ->with('leader')
        ->get();

        return response()->json($teams);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string'
        ]);

        $team = Team::create([
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'leader_id' => $request->user()->id,
        ]);

        $team->members()->syncWithoutDetaching([
            $request->user()->id => [
                'joined_at' => now(),
            ]
        ]);

        return response()->json($team, 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(Team $team)
    {
        Gate::authorize('view', $team);

        $team->load([
            'leader',
            'members'
        ]);

        return response()->json($team);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Team $team)
    {
        Gate::authorize('update', $team);

        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'description' => 'sometimes|nullable|string'
        ]);

        $team ->update($validated);
        return response()->json($team, 200);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Team $team)
    {
        Gate::authorize('delete', $team);

        $team->delete();

        return response()->noContent();
    }

    /**
     * Invite a user to the team by email.
     */
    public function invite(Request $request, Team $team) 
    {
        Gate::authorize('manageMembers', $team);

        $validated = $request->validate([
            'email' => 'required|email|exists:users,email',
        ]);

        $userToInvite = User::where('email', $validated['email'])->firstOrFail();

        $team->members()->syncWithoutDetaching([
            $userToInvite->id => [
                'joined_at' => now(),
            ]
        ]);

        return response()->json([
            'message' => 'User was removed from the team successfully.',
            'user' => $userToInvite
        ], 200);
    }

    /**
     * Remove a member from the team.
     */
    public function removeMember(Request $request, Team $team, User $user) 
    {
        Gate::authorize('manageMembers', $team);

        if ($team->leader_id === $user->id) {
            return response()->json([
                'error' => 'You are not allowed to perform this action.'
            ], 422);
        }

        $team->members()->detach($user->id);

        return response()->json([
            'message' => 'User was successfully removed from the team.'
        ], 200);
    }
}
