<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class OrganizationMembershipController extends Controller
{
    private function authorizeOrganizationManagement(Request $request): void
    {
        $organization = $request->user()->organization;

        if (! $organization) {
            abort(403, 'You are not part of an organization');
        }

        $this->authorize('manageMembers', $organization);
    }

    public function pendingMembers(Request $request)
    {
        $this->authorizeOrganizationManagement($request);

        $organizationId = $request->user()->organization_id;

        $rows = DB::table('user_roles')
            ->whereNull('granted_by')
            ->join('users', 'users.id', '=', 'user_roles.user_id')
            ->join('roles', 'roles.id', '=', 'user_roles.role_id')
            ->where('roles.slug', 'company')
            ->where('users.organization_id', $organizationId)
            ->select(
                'users.id',
                'users.first_name',
                'users.last_name',
                'users.email',
                'users.status',
                'users.created_at',
                'roles.slug as role_slug',
                'roles.name as role_name',
                'user_roles.id as role_id'
            )
            ->orderBy('users.created_at', 'desc')
            ->get();

        return response()->json($rows);
    }

    public function approveMember(Request $request, int $userId)
    {
        $this->authorizeOrganizationManagement($request);

        $organizationId = $request->user()->organization_id;

        $targetUser = User::findOrFail($userId);
        if ($targetUser->organization_id !== $organizationId) {
            return response()->json(['message' => 'Unauthorized - user not in your organization'], 403);
        }

        $updated = DB::table('user_roles')
            ->where('user_id', $userId)
            ->whereNull('granted_by')
            ->whereIn('role_id', function ($query) {
                $query->select('id')
                    ->from('roles')
                    ->where('slug', 'company');
            })
            ->update([
                'granted_by' => $request->user()->id,
                'granted_at' => now(),
            ]);

        if (! $updated) {
            return response()->json(['message' => 'No pending role found for this user'], 404);
        }

        return response()->json(['message' => 'Member approved']);
    }

    public function rejectMember(Request $request, int $userId)
    {
        $this->authorizeOrganizationManagement($request);

        $organizationId = $request->user()->organization_id;

        $targetUser = User::findOrFail($userId);
        if ($targetUser->organization_id !== $organizationId) {
            return response()->json(['message' => 'Unauthorized - user not in your organization'], 403);
        }

        $deleted = DB::table('user_roles')
            ->where('user_id', $userId)
            ->whereNull('granted_by')
            ->whereIn('role_id', function ($query) {
                $query->select('id')
                    ->from('roles')
                    ->where('slug', 'company');
            })
            ->delete();

        if (! $deleted) {
            return response()->json(['message' => 'No pending role found for this user'], 404);
        }

        return response()->json(['message' => 'Member request rejected']);
    }

    public function activeMembers(Request $request)
    {
        $this->authorizeOrganizationManagement($request);

        $organizationId = $request->user()->organization_id;

        $rows = DB::table('user_roles')
            ->whereNotNull('user_roles.granted_by')
            ->join('users', 'users.id', '=', 'user_roles.user_id')
            ->join('roles', 'roles.id', '=', 'user_roles.role_id')
            ->where('roles.slug', 'company')
            ->where('users.organization_id', $organizationId)
            ->select(
                'users.id',
                'users.first_name',
                'users.last_name',
                'users.email',
                'users.status',
                'users.created_at',
                'users.role_in_org',
                'roles.slug as role_slug'
            )
            ->orderBy('users.first_name', 'asc')
            ->get();

        $formatted = $rows->map(function ($row) {
            return [
                'id' => $row->id,
                'first_name' => $row->first_name,
                'last_name' => $row->last_name,
                'email' => $row->email,
                'status' => $row->status,
                'role_in_org' => $row->role_in_org,
                'roles' => [
                    ['id' => 1, 'slug' => $row->role_slug],
                ],
            ];
        });

        return response()->json($formatted);
    }

    public function kickMember(Request $request, int $userId)
    {
        $this->authorizeOrganizationManagement($request);

        $organizationId = $request->user()->organization_id;

        $targetUser = User::findOrFail($userId);
        if ($targetUser->organization_id !== $organizationId) {
            return response()->json(['message' => 'Unauthorized - user not in your organization'], 403);
        }

        $updated = DB::table('user_roles')
            ->where('user_id', $userId)
            ->whereNotNull('granted_by')
            ->whereIn('role_id', function ($query) {
                $query->select('id')->from('roles')->where('slug', 'company');
            })
            ->update([
                'granted_by' => null,
            ]);

        if (! $updated) {
            return response()->json(['message' => 'No active role found for this user'], 404);
        }

        return response()->json(['message' => 'Member kicked successfully, now pending']);
    }

    public function updateMemberRole(Request $request, int $userId)
    {
        $this->authorizeOrganizationManagement($request);

        if ($request->user()->id == $userId) {
            abort(403, 'You cannot modify your own role');
        }

        $organizationId = $request->user()->organization_id;

        $data = $request->validate([
            'role_in_org' => 'required|string|in:contact,evaluator,mentor',
        ]);

        $targetUser = User::findOrFail($userId);
        if ($targetUser->organization_id !== $organizationId) {
            return response()->json(['message' => 'Unauthorized - user not in your organization'], 403);
        }

        $hasActiveCompanyRole = DB::table('user_roles')
            ->where('user_id', $userId)
            ->whereNotNull('granted_by')
            ->whereIn('role_id', function ($query) {
                $query->select('id')->from('roles')->where('slug', 'company');
            })
            ->exists();

        if (!$hasActiveCompanyRole) {
            return response()->json(['message' => 'User does not have an active company role'], 422);
        }

        $targetUser->update([
            'role_in_org' => $data['role_in_org']
        ]);

        return response()->json([
            'message' => 'Member role within organization updated successfully',
            'role_in_org' => $targetUser->role_in_org
        ]);
    }
}
