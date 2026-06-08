<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

use App\Models\User;
use App\Mail\RegistrationSubmit;

/**
 * @tags Authentication Management
 * Endpoints for verifying user email addresses via secure signed links, fulfilling registration requirements, and resending activation emails.
 */
class EmailVerificationController extends Controller
{
    public function completeRegistration(Request $request, int $id, string $hash) {
        $user = User::findOrFail($id);

        if (! hash_equals(sha1($user->email), $hash)) {
            return response()->json(['message' => 'Invalid verification link'], 403);
        }
        if (! $request->hasValidSignature()) {
            return response()->json(['message' => 'Link expired'], 403);
        }

        $superAdmin = User::join('user_roles', 'users.id', '=', 'user_roles.user_id')
            ->join('roles', 'user_roles.role_id', '=', 'roles.id')
            ->where('roles.slug', 'super_admin')
            ->select('users.id')
            ->first();

        if (! $superAdmin) {
            return response()->json(['message' => 'System error: no super admin found'], 500);
        }

        DB::transaction(function () use ($user, $superAdmin) {
            $user->update(['email_verified_at' => now(), 'status' => 'active']);

            if ($user->isStudent()) {
                DB::table('user_roles')
                ->where('user_id', $user->id)
                ->update([
                    'granted_by' => $superAdmin->id,
                    'granted_at' => now(),
                ]);
            }

            if ($user->role_in_org === 'owner') {
                $user->update(['email_verified_at' => now(), 'status' => 'pending_approval']);
            }

        });

        return redirect(rtrim(env('FRONTEND_URL', 'http://localhost:5173'), '/').'/verified');
    }

    public function resendVerificationEmail(Request $request) {
        $user = $request->user();

        if ($user->email_verified_at) {
            return response()->json(['message' => 'Already verified'], 400);
        }
        Mail::to($user->email)->queue(new RegistrationSubmit($user));

        return response()->json(['message' => 'Verification email sent']);
    }
}
