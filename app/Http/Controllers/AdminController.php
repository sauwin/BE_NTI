<?php

namespace App\Http\Controllers;

use App\Models\CompanyProfile;
use App\Models\MentorProfile;
use App\Models\Role;
use App\Models\StudentProfile;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

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
     * Get user profile with related data.
     */
    public function showUser(int $id)
    {
        $user = User::with('roles')->findOrFail($id);

        $data = [
            'user' => $user,
            'student_profile' => null,
            'mentor_profile' => null,
            'company_profile' => null,
        ];

        if ($user->roles()->where('slug', 'student')->exists()) {
            $data['student_profile'] = StudentProfile::where('user_id', $id)->first();
        }

        if ($user->roles()->where('slug', 'mentor')->exists()) {
            $data['mentor_profile'] = MentorProfile::where('user_id', $id)->first();
        }

        if ($user->roles()->where('slug', 'company')->exists()) {
            $data['company_profile'] = CompanyProfile::where('user_id', $id)->first();
        }

        return response()->json($data);
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

        if (! $updated) {
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

    /**
     * Create new admin user (super-admin only).
     */
    public function createAdmin(Request $request)
    {
        $data = $request->validate([
            'first_name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',
            'email' => 'required|email|unique:users',
            'password' => 'required|string|min:8',
            'role' => 'required|in:nti_admin,evaluator,content_editor',
        ]);

        $user = User::create([
            'first_name' => $data['first_name'],
            'last_name' => $data['last_name'],
            'email' => $data['email'],
            'password_hash' => Hash::make($data['password']),
            'status' => 'active',
            'email_verified_at' => now(),
        ]);

        $role = Role::where('slug', $data['role'])->firstOrFail();
        DB::table('user_roles')->insert([
            'user_id' => $user->id,
            'role_id' => $role->id,
            'granted_by' => $request->user()->id,
            'granted_at' => now(),
        ]);

        return response()->json(['message' => 'Admin created', 'user' => $user], 201);
    }

    /**
     * Assign role to existing user (admin can assign non-admin roles).
     */
    public function assignRole(Request $request, int $userId)
    {
        $data = $request->validate([
            'role' => 'required|in:student,company,mentor,evaluator,content_editor',
        ]);

        $user = User::findOrFail($userId);
        $role = Role::where('slug', $data['role'])->firstOrFail();

        if ($user->roles()->where('role_id', $role->id)->exists()) {
            return response()->json(['message' => 'User already has this role'], 422);
        }

        $existingRole = DB::table('user_roles')->where('user_id', $user->id)->first();
        if ($existingRole) {
            DB::table('user_roles')
                ->where('user_id', $user->id)
                ->update([
                    'role_id' => $role->id,
                    'granted_by' => $request->user()->id,
                    'granted_at' => now(),
                ]);
        } else {
            DB::table('user_roles')->insert([
                'user_id' => $user->id,
                'role_id' => $role->id,
                'granted_by' => $request->user()->id,
                'granted_at' => now(),
            ]);
        }

        return response()->json(['message' => 'Role assigned']);
    }

    /**
     * Remove role from user (admin can remove non-admin roles).
     */
    public function removeRole(Request $request, int $userId)
    {
        $data = $request->validate([
            'role' => 'required|string',
        ]);

        // Prevent removing admin roles
        if (in_array($data['role'], ['nti_admin', 'super_admin'])) {
            return response()->json(['message' => 'Cannot remove admin roles'], 403);
        }

        $user = User::findOrFail($userId);
        $role = Role::where('slug', $data['role'])->firstOrFail();

        $deleted = DB::table('user_roles')
            ->where('user_id', $user->id)
            ->where('role_id', $role->id)
            ->delete();

        if (! $deleted) {
            return response()->json(['message' => 'User does not have this role'], 404);
        }

        return response()->json(['message' => 'Role removed']);
    }

    /**
     * Delete user (super-admin only).
     */
    public function deleteUser(int $userId)
    {
        $user = User::findOrFail($userId);

        // Prevent deleting admin users
        if ($user->roles()->whereIn('slug', ['nti_admin', 'super_admin'])->exists()) {
            return response()->json(['message' => 'Cannot delete admin users'], 403);
        }

        DB::table('user_roles')->where('user_id', $userId)->delete();
        $user->delete();

        return response()->json(['message' => 'User deleted']);
    }
}
