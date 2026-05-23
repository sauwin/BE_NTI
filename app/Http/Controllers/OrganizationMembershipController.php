<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

use Http\App\Models\User;

class OrganizationMembershipController extends Controller
{
    
    /**
     * Get pending company member approvals for the current user's organization.
     */
    public function pendingMembers(Request $request)
    {
        $user = $request->user();
        $organizationId = $user->organization_id;

        if (!$organizationId) {
            return response()->json(['message' => 'You are not part of an organization'], 403);
        }

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

    /**
     * Approve a company member's role.
     */
    public function approveMember(Request $request, int $userId)
    {
        $user = $request->user();
        $organizationId = $user->organization_id;

        if (!$organizationId) {
            return response()->json(['message' => 'You are not part of an organization'], 403);
        }

        // Verify the user being approved belongs to the same organization
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
                'granted_by' => $user->id,
                'granted_at' => now(),
            ]);

        if (!$updated) {
            return response()->json(['message' => 'No pending role found for this user'], 404);
        }

        return response()->json(['message' => 'Member approved']);
    }

    /**
     * Reject a company member's role request.
     */
    public function rejectMember(Request $request, int $userId)
    {
        $user = $request->user();
        $organizationId = $user->organization_id;

        if (!$organizationId) {
            return response()->json(['message' => 'You are not part of an organization'], 403);
        }

        // Verify the user being rejected belongs to the same organization
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

        if (!$deleted) {
            return response()->json(['message' => 'No pending role found for this user'], 404);
        }

        return response()->json(['message' => 'Member request rejected']);
    }

    /**
     * Get active (approved) company members.
     */
    public function activeMembers(Request $request)
    {
        $user = $request->user();
        $organizationId = $user->organization_id;

        if (!$organizationId) {
            return response()->json(['message' => 'You are not part of an organization'], 403);
        }

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
                'roles' => [
                    ['id' => 1, 'slug' => $row->role_slug]
                ]
            ];
        });

        return response()->json($formatted);
    }

    /**
     * Kick a member out of the company (make them pending again or delete role).
     */
    public function kickMember(Request $request, int $userId)
    {
        $user = $request->user();
        $organizationId = $user->organization_id;

        if (!$organizationId) {
            return response()->json(['message' => 'You are not part of an organization'], 403);
        }

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
                'granted_at' => null,
            ]);

        if (!$updated) {
            return response()->json(['message' => 'No active role found for this user'], 404);
        }

        return response()->json(['message' => 'Member kicked successfully, now pending']);
    }
}
