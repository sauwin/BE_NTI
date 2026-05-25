<?php

namespace App\Http\Controllers;

use App\Models\Application;
use App\Models\GdprConsent;
use App\Models\StudentProfile;
use App\Services\AuditService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class GdprController extends Controller
{
    /**
     * POST /gdpr/consent
     */
    public function store(Request $request)
    {
        $data = $request->validate([
            'purpose' => 'required|string|max:255',
            'version' => 'nullable|string|max:50',
        ]);

        $consent = GdprConsent::create([
            'user_id' => $request->user()->id,
            'purpose' => $data['purpose'],
            'version' => $data['version'] ?? '1.0',
            'ip_address' => $request->ip(),
            'consented_at' => now(),
        ]);

        return response()->json($consent, 201);
    }

    /**
     * GET /gdpr/consents
     */
    public function index(Request $request)
    {
        $consents = GdprConsent::where('user_id', $request->user()->id)
            ->orderByDesc('consented_at')
            ->get();

        return response()->json($consents);
    }

    /**
     * POST /gdpr/export
     */
    public function export(Request $request)
    {
        $user = $request->user()->load(['roles', 'organization']);

        $profile = StudentProfile::where('user_id', $user->id)->first();

        $applications = Application::where('student_profile_id', $profile?->id)
            ->with(['call', 'team'])
            ->get()
            ->map(fn ($app) => [
                'id' => $app->id,
                'program_type' => $app->program_type,
                'status' => $app->status,
                'submitted_at' => $app->created_at?->toIso8601String(),
                'call' => $app->call?->name,
                'team' => $app->team?->name,
            ]);

        $consents = GdprConsent::where('user_id', $user->id)
            ->orderByDesc('consented_at')
            ->get(['purpose', 'version', 'ip_address', 'consented_at', 'withdrawn_at']);

        $export = [
            'exported_at' => now()->toIso8601String(),
            'user' => [
                'id' => $user->id,
                'first_name' => $user->first_name,
                'last_name' => $user->last_name,
                'email' => $user->email,
                'status' => $user->status,
                'created_at' => $user->created_at?->toIso8601String(),
                'roles' => $user->roles->pluck('slug'),
            ],
            'student_profile' => $profile ? [
                'study_program' => $profile->study_program,
                'year_of_study' => $profile->year_of_study,
                'university' => $profile->university,
                'bio' => $profile->bio,
                'github_url' => $profile->github_url,
            ] : null,
            'applications' => $applications,
            'gdpr_consents' => $consents,
        ];

        AuditService::log('gdpr_export', 'user', [
            'target_user_id' => $user->id,
        ]);

        return response()->json($export)
            ->header('Content-Disposition', 'attachment; filename="my-data-export.json"');
    }

    /**
     * DELETE /gdpr/account
     */
    public function anonymize(Request $request)
    {
        $request->validate([
            'confirm' => 'required|in:DELETE MY ACCOUNT',
        ]);

        $user = $request->user();

        $userId = $user->id;

        DB::transaction(function () use ($user) {
            // Revoke all tokens
            $user->tokens()->delete();

            // Anonymize PII fields
            $user->update([
                'first_name' => 'Deleted',
                'last_name' => 'User',
                'email' => 'deleted_'.$user->id.'@anonymized.local',
                'password_hash' => bcrypt(Str::random(32)),
                'status' => 'deleted',
                'email_verified_at' => null,
                'organization_id' => null,
                'role_in_org' => null,
            ]);

            // Mark consent as withdrawn
            GdprConsent::where('user_id', $user->id)
                ->whereNull('withdrawn_at')
                ->update(['withdrawn_at' => now()]);

            // Anonymize student profile
            StudentProfile::where('user_id', $user->id)->update([
                'bio' => null,
                'github_url' => null,
            ]);

            // Remove roles
            DB::table('user_roles')->where('user_id', $user->id)->delete();
        });

        AuditService::log('gdpr_anonymize', 'user', [
            'target_user_id' => $userId,
        ]);

        return response()->json(['message' => 'Account anonymized successfully.']);
    }
}
