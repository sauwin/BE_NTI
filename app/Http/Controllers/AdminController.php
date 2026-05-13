<?php

namespace App\Http\Controllers;

use App\Models\Role;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AdminController extends Controller
{
    /**
     * List all users with their roles and approval status.
     */
    public function users(Request $request)
    {
        $users = User::select('id', 'first_name', 'last_name', 'email', 'status', 'created_at')
            ->with(['roles' => function ($q) {
                $q->select('roles.id', 'roles.name', 'roles.slug',
                    'user_roles.granted_by', 'user_roles.granted_at');
            }])
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json($users);
    }

    /**
     * List users whose role is not yet approved (granted_by = null).
     */
    public function pendingApprovals()
    {
        $rows = DB::table('user_roles')
            ->whereNull('granted_by')
            ->join('users', 'users.id', '=', 'user_roles.user_id')
            ->join('roles', 'roles.id', '=', 'user_roles.role_id')
            ->select(
                'users.id',
                'users.first_name',
                'users.last_name',
                'users.email',
                'users.status',
                'users.created_at',
                'roles.slug as role_slug',
                'roles.name as role_name'
            )
            ->orderBy('users.created_at', 'desc')
            ->get();

        return response()->json($rows);
    }

    /**
     * Approve a user's role (set granted_by to current admin).
     */
    public function approveRole(Request $request, int $userId)
    {
        $updated = DB::table('user_roles')
            ->where('user_id', $userId)
            ->whereNull('granted_by')
            ->update([
                'granted_by' => $request->user()->id,
                'granted_at' => now(),
            ]);

        if (!$updated) {
            return response()->json(['message' => 'No pending role found for this user'], 404);
        }

        return response()->json(['message' => 'Role approved']);
    }

    /**
     * Block a user.
     */
    public function blockUser(int $userId)
    {
        User::where('id', $userId)->update(['status' => 'blocked']);
        return response()->json(['message' => 'User blocked']);
    }
}