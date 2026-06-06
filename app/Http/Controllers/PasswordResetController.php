<?php

namespace App\Http\Controllers;

use App\Mail\PasswordResetMail;
use App\Models\PasswordResetToken;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;

/**
 * @tags Authentication Management
 * Endpoints for managing secure password recovery workflows, including initiating tokenized reset requests via email, verifying token validity, and applying new user credentials.
 */
class PasswordResetController extends Controller
{
    /**
     * Drop the password (avoid the admin)
     */
    public function forgot(Request $request)
    {
        $data = $request->validate(['email' => 'required|email|exists:users']);
        $user = User::where('email', $data['email'])->first();

        if ($user->roles()->whereIn('slug', ['nti_admin', 'super_admin'])->exists()) {
            return response()->json(['message' => 'Admin passwords cannot be reset via this method'], 403);
        }

        DB::transaction(function () use ($user) {
            PasswordResetToken::where('user_id', $user->id)->delete();
            $token = Str::random(64);
            PasswordResetToken::create([
                'user_id' => $user->id,
                'token' => hash('sha256', $token),
                'expires_at' => now()->addHour(),
            ]);
            Mail::to($user->email)->queue(new PasswordResetMail($user, $token));
        });

        return response()->json(['message' => 'Password reset link sent to email'], 200);
    }

    /**
     * Set new password for user
     */
    public function reset(Request $request)
    {
        $data = $request->validate([
            'token' => 'required|string',
            'password' => ['required', 'string', 'confirmed', Password::min(8)->mixedCase()->numbers()->symbols()],
        ]);

        $record = PasswordResetToken::where('token', hash('sha256', $data['token']))->first();

        if (! $record || $record->isExpired()) {
            return response()->json(['message' => 'Invalid or expired token'], 400);
        }

        $user = $record->user;
        if ($user->roles()->whereIn('slug', ['nti_admin', 'super_admin'])->exists()) {
            return response()->json(['message' => 'Admin passwords cannot be reset via this method'], 403);
        }

        DB::transaction(function () use ($user, $data, $record) {
            $user->update(['password' => Hash::make($data['password'])]);
            $record->delete();
        });

        return response()->json(['message' => 'Password reset successfully'], 200);
    }

    /**
     * Verify token for reseting the password
     */
    public function verify(Request $request)
    {
        $data = $request->validate(['token' => 'required|string']);
        $record = PasswordResetToken::where('token', hash('sha256', $data['token']))->first();

        if (! $record || $record->isExpired()) {
            return response()->json(['valid' => false]);
        }

        return response()->json(['valid' => true]);
    }
}
