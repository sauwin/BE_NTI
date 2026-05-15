<?php

namespace App\Http\Controllers;

use App\Mail\RegistrationSubmit;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;

class AuthController extends Controller
{
    public function register(Request $request)
    {
        $data = $request->validate([
            'first_name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'password' => 'required|string|min:8|confirmed',
            'role' => 'required|in:student,company,mentor',
        ]);

        if ($data['role'] === 'student') {
            $allowedDomains = explode(',', env('STUDENT_ALLOWED_DOMAINS', ''));
            $emailDomain = substr(strrchr($data['email'], '@'), 1);
            if (! in_array($emailDomain, $allowedDomains)) {
                return response()->json([
                    'message' => 'Students must register with a university email.',
                    'errors' => ['email' => ['Email domain not allowed. Use ukf.sk or spu.sk address.']],
                ], 422);
            }
        }

        $user = DB::transaction(function () use ($data) {
            $user = User::create([
                'first_name' => $data['first_name'],
                'last_name' => $data['last_name'],
                'email' => $data['email'],
                'password_hash' => Hash::make($data['password']),
                'status' => 'pending_verification',
            ]);

            $role = Role::where('slug', $data['role'])->firstOrFail();
            DB::table('user_roles')->insert([
                'user_id' => $user->id,
                'role_id' => $role->id,
                'granted_by' => null,
                'granted_at' => now(),
            ]);

            return $user;
        });

        Mail::to($user->email)->send(new RegistrationSubmit($user));
        NotificationController::log($user->id, $user->email, 'registration',
            'Welcome to NTI! Please verify your email to continue.',
            ['email' => $user->email]
        );
        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json(['token' => $token, 'user' => $this->userWithRole($user)], 201);
    }

    public function login(Request $request)
    {
        $data = $request->validate([
            'email' => 'required|email',
            'password' => 'required|string',
        ]);

        $user = User::where('email', $data['email'])->first();

        if (! $user || ! Hash::check($data['password'], $user->password_hash)) {
            return response()->json(['message' => 'Invalid credentials'], 401);
        }

        if ($user->status === 'pending_verification') {
            return response()->json(['message' => 'pending_verification'], 403);
        }

        if ($user->status === 'blocked') {
            return response()->json(['message' => 'blocked'], 403);
        }

        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json(['token' => $token, 'user' => $this->userWithRole($user)]);
    }

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'Logged out']);
    }

    public function me(Request $request)
    {
        return response()->json($this->userWithRole($request->user()));
    }

    private function userWithRole(User $user): array
    {
        $row = DB::table('user_roles')->where('user_id', $user->id)->first();
        $role = $row ? Role::find($row->role_id) : null;
        $data = $user->toArray();
        $data['role_slug'] = $role?->slug;

        return $data;
    }

    public function roleStatus(Request $request)
    {
        $row = DB::table('user_roles')
            ->where('user_id', $request->user()->id)
            ->first();

        return response()->json([
            'approved' => $row && $row->granted_by !== null,
        ]);
    }
}
