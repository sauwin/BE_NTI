<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

use App\Models\Team;
use App\Models\User;

/**
 * @tags Team Collaboration
 * Endpoints for managing student team lifecycles, including team creation, member invitations, invitation response workflows, and participant management during the 'forming' state.
 */
class TeamController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $teams = $request->user()
        ->teams()
        ->with(['leader', 'members'])
        ->withCount('members')
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
            'status' => 'forming'
        ]);

        $team->members()->syncWithoutDetaching([
            $request->user()->id => [
                'status' => 'accepted',
                'joined_at' => now(),
            ]
        ]);

        return response()->json($team->load('members'), 201);
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

        if ($team->status !== 'forming') {
            return response()->json([
                'message' => 'You cannot delete ready teams.'
            ], 422);
        }

        $team->delete();

        return response()->noContent();
    }

    public function invite(Request $request, Team $team) 
    {
        Gate::authorize('manageMembers', $team);

        if ($team->status !== 'forming') {
            return response()->json([
                'message' => 'You cannot invite members to ready team.'
            ], 422);
        }

        $validated = $request->validate([
            'email' => 'required|email|exists:users,email',
        ]);

        $userToInvite = User::where('email', $validated['email'])->firstOrFail();

        if (!$userToInvite->isStudent()) {
            return response()->json([
                'message' => 'You can only invite users who have the student role.'
            ], 422);
        }

        $alreadyMember = $team->members()->where('user_id', $userToInvite->id)->exists();
        if ($alreadyMember) {
            return response()->json([
                'message' => 'User is already a member or has a pending invitation.'
            ], 422);
        }

        $team->members()->syncWithoutDetaching([
            $userToInvite->id => [
                'status' => 'pending',
                'joined_at' => null
            ]
        ]);

        return response()->json([
            'message' => 'Invitation sent successfully.',
            'user' => $userToInvite
        ], 200);
    }

    /**
     * Remove a member from the team.
     */
    public function removeMember(Request $request, Team $team, User $user) 
    {
        Gate::authorize('manageMembers', $team);

        if ($team->status !== 'forming') {
            return response()->json([
                'message' => 'You cannot remove members in ready teams.'
            ], 422);
        }

        if ($team->leader_id === $user->id) {
            return response()->json([
                'error' => 'You cannot remove the team leader.'
            ], 403); 
        }

        $team->members()->detach($user->id);

        return response()->json([
            'message' => 'User was successfully removed from the team.'
        ], 200);
    }

    public function myInvitations(Request $request)
    {
        $invitations = $request->user()
            ->teams()
            ->wherePivot('status', 'pending')
            ->with('leader')
            ->get();

        return response()->json($invitations);
    }

    public function respondToInvitation(Request $request, Team $team)
    {
        $validated = $request->validate([
            'status' => 'required|in:accepted,rejected',
        ]);

        if ($team->status !== 'forming') {
            return response()->json([
                'message' => 'You cannot accept the invitation, because this team is in state ready.'
            ], 422);
        }

        $user = $request->user();

        $membership = $team->members()->where('user_id', $user->id)->first();

        if (!$membership || $membership->pivot->status !== 'pending') {
            return response()->json([
                'message' => 'You do not have a pending invitation to this team.'
            ], 404);
        }

        if ($validated['status'] === 'accepted') {
            $team->members()->updateExistingPivot($user->id, [
                'status' => 'accepted',
                'joined_at' => now(),
            ]);

            return response()->json([
                'message' => 'You have successfully joined the team.'
            ], 200);
        } else {
            $team->members()->detach($user->id);

            return response()->json([
                'message' => 'Invitation declined successfully.'
            ], 200);
        }
    }
}
