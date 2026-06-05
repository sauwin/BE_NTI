<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;

use App\Mail\RegistrationSubmit;
use App\Models\Organization;
use App\Models\Role;
use App\Models\User;
use App\Models\GdprConsent; 
use App\Http\Resources\UserResource;

/**
 * @tags Authentication
 * Endpoints for user authentication, Sanctum session management, and token verification.
 */
class AuthController extends Controller
{
    public function register(Request $request)
    {
        // Validation
        $data = $request->validate([
            'first_name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'password' => 'required|string|min:8|confirmed',
            'role' => 'required|in:student,company',
            'role_in_org' => 'required_if:role,company|in:owner,member',
            'registration_number' => 'required_if:role_in_org,member|nullable|integer|exists:organizations,registration_number',
            'organization_name' => 'nullable|string|max:255',
            'sector' => 'nullable|string|max:100',
            'description' => 'nullable|string|max:2000',
            'website' => 'nullable|url|max:255',
            'agreed_terms' => 'required|accepted',
            'gdpr_consent' => 'required|accepted',
        ]);

        if ($data['role'] === 'student') {
            $allowedDomains = explode(',', env('STUDENT_ALLOWED_DOMAINS', ''));
            $emailDomain = substr(strrchr($data['email'], '@'), 1);
            if (! in_array($emailDomain, $allowedDomains)) {
                return response()->json([
                    'message' => 'Students must register with a university email.',
                    'errors' => ['email' => ['Email domain not allowed. Try ukf.sk or spu.sk address.']],
                ], 422);
            }
        }

        // Models creation
        $user = DB::transaction(function () use ($data, $request) {
            $organization_id = null;

            if ($data['role'] === 'company'){ 

                if ($data['role_in_org'] === 'owner' && ! empty($data['organization_name'])) {
                    $organization = Organization::create([
                        'name' => $data['organization_name'],
                        'registration_number' => $data['registration_number'] ?? null,
                        'sector' => $data['sector'] ?? null,
                        'description' => $data['description'] ?? null,
                        'website' => $data['website'] ?? null,
                        'status' => 'pending',
                        'is_public_partner' => false,
                    ]);

                    $organization_id = $organization->id;
                }

                if ($data['role_in_org'] === 'member') {
                    $organization_id = Organization::where(
                        'registration_number',
                        $data['registration_number']
                    )->value('id');
                }

                //Mapping 'member' to contact role
                $roleInOrg = match ($data['role_in_org']) {
                    'owner' => 'owner',
                    'member' => 'contact',
                };
            }

            $user = User::create([
                'first_name' => $data['first_name'],
                'last_name' => $data['last_name'],
                'email' => $data['email'],
                'password' => Hash::make($data['password']),
                'status' => 'pending_verification',
                'organization_id' => $organization_id,
                'role_in_org' => $roleInOrg ?? null,
            ]);

            GdprConsent::create([
                'user_id' => $user->id,
                'purpose' => 'registration_general_terms_and_gdpr',
                'version' => '1.0',
                'ip_address' => $request->ip() ?? '127.0.0.1',
                'consented_at' => now(),
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

        // Mail and notification
        Mail::to($user->email)->queue(new RegistrationSubmit($user));
        NotificationController::log($user->id, $user->email, 'registration',
            'Welcome to NTI! Please verify your email to continue.',
            ['email' => $user->email]
        );

        // Response
        $token = $user->createToken('auth_token')->plainTextToken;
        $user->load(['roles', 'organization']);

        return response()->json([
            'token' => $token,
            'user' => new UserResource($user),
        ], 201);
    }

    public function login(Request $request)
    {
        $data = $request->validate([
            'email' => 'required|email',
            'password' => 'required|string',
        ]);

        $user = User::where('email', $data['email'])->first();

        if (! $user || ! Hash::check($data['password'], $user->password)) {
            return response()->json(['message' => 'Invalid credentials'], 401);
        }

        if ($user->status === 'pending_verification') {
            return response()->json(['message' => 'pending_verification'], 403);
        }

        if ($user->status === 'blocked') {
            return response()->json(['message' => 'blocked'], 403);
        }

        $token = $user->createToken('auth_token')->plainTextToken;

        $user->load(['roles', 'organization']);

        return response()->json([
            'token' => $token,
            'user' => new UserResource($user),
        ]);
    }

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'Logged out']);
    }

    public function me(Request $request)
    {
        $user = $request->user()->load([
            'roles',
            'organization',
        ]);

        return new UserResource($user);
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