<?php

namespace App\Http\Controllers;

use App\Mail\ApplicationSubmittedMail;
use App\Mail\ProjectClosedMail;
use App\Mail\StatusChangedMail;
use App\Models\Application;
use App\Models\Call;
use App\Models\Document;
use App\Models\StudentProfile;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class ApplicationController extends Controller
{
    public function store(Request $request)
    {
        $data = $request->validate([
            'applicant_type' => 'required|in:student,team',
            'program_type' => 'required|in:a,b',
            'team_id' => 'nullable|exists:teams,id',
            'submit_type' => 'nullable|in:draft,final',
        ]);

        $isFinalSubmit = ($data['submit_type'] ?? 'final') === 'final';

        if ($data['applicant_type'] === 'team' && empty($data['team_id'])) {
            return response()->json([
                'message' => 'Team specification is required for team aplication'
            ], 422);
        }

        $call = Call::whereHas('program', fn ($q) => $q->where('code', 'program_'.$data['program_type']))
            ->where('status', 'open')
            ->latest()
            ->first();

        if (! $call) {
            return response()->json(['message' => 'No active call found for this program.'], 422);
        }

        $profile = StudentProfile::where('user_id', $request->user()->id)->first();

        if (! $profile) {
            return response()->json([
                'message' => 'Pred odoslaním prihlášky si musíte vytvoriť študentský profil.'
            ], 403);
        }

        $team = null;

        if ($data['applicant_type'] === 'team') {
            $team = \App\Models\Team::withCount(['members' => function ($query) {
                $query->where('team_members.status', 'accepted'); 
            }])->find($data['team_id']);

            if ($isFinalSubmit && $team->status !== 'forming') {
                return response()->json([
                    'message' => 'Táto prihláška nemôže byť odoslaná, pretože tím už je v stave ready (uzamknutý).'
                ], 422);
            }

            if ($team->leader_id !== $request->user()->id) {
                return response()->json([
                    'message' => 'Iba líder tímu môže podať prihlášku za tento tím.'
                ], 403);
            }

            if ($isFinalSubmit && $team->members_count < 3) {
                return response()->json([
                    'message' => 'Tím mustí mať minimálne 3 členov s potvrdeným statusom (accepted) pre prihlásenie do Programu A.'
                ], 422);
            }
        }

        $maxApps = (int) env('MAX_ACTIVE_APPLICATIONS', 0);
        if ($maxApps > 0 && $isFinalSubmit) {
            $activeCount = Application::where('student_profile_id', $profile?->id)
                ->whereNotIn('status', ['rejected', 'closed', 'draft'])
                ->count();
            if ($activeCount >= $maxApps) {
                return response()->json([
                    'message' => 'You have reached the maximum number of active applications.',
                ], 422);
            }
        }

        $application = DB::transaction(function () use ($call, $data, $profile, $team, $isFinalSubmit) {
            $app = Application::create([
                'call_id' => $call->id,
                'applicant_type' => $data['applicant_type'],
                'program_type' => $data['program_type'],
                'team_id' => $data['team_id'] ?? null,
                'student_profile_id' => $profile?->id,
                'status' => $isFinalSubmit ? 'submitted' : 'draft',
            ]);

            if ($team && $isFinalSubmit) {
                $team->update(['status' => 'ready']);
            }

            return $app;
        });

        if ($isFinalSubmit) {
            Mail::to($request->user()->email)->queue(
                new ApplicationSubmittedMail($request->user(), $application)
            );

            NotificationController::log($request->user()->id, $request->user()->email, 'application_submitted',
                'Your application #'.$application->id.' for Program '.strtoupper($application->program_type).' was submitted.',
                ['application_id' => $application->id]
            );
        }

        return response()->json(['application_id' => $application->id], 201);
    }

    public function updateStatus(Request $request, int $id)
    {
        $data = $request->validate([
            'status' => 'required|in:draft,submitted,formal_check,formally_verified,under_evaluation,pending_revision,approved,rejected,onboarding,active,suspended,closed',
        ]);

        $application = Application::findOrFail($id);
        $oldStatus = $application->status;
        $application->update(['status' => $data['status']]);

        $user = $request->user();

        if ($data['status'] === 'closed') {
            Mail::to($user->email)->queue(new ProjectClosedMail($user, $application));
            NotificationController::log($user->id, $user->email, 'project_closed',
                'Your project #'.$application->id.' has been closed.',
                ['application_id' => $application->id]
            );
        } else {
            Mail::to($user->email)->queue(new StatusChangedMail($user, $application, $oldStatus));
            NotificationController::log($user->id, $user->email, 'status_changed',
                'Your application #'.$application->id.' status changed from '.$oldStatus.' to '.$data['status'].'.',
                ['application_id' => $application->id, 'old_status' => $oldStatus, 'new_status' => $data['status']]
            );
        }

        return response()->json(['status' => $application->status]);
    }

    public function index(Request $request)
    {
        $profile = StudentProfile::where('user_id', $request->user()->id)->first();
        if (! $profile) {
            return response()->json([]);
        }

        $applications = Application::where('student_profile_id', $profile->id)
            ->orderByDesc('created_at')
            ->get();

        return response()->json($applications);
    }

    public function show(Request $request, int $id)
    {
        $profile = StudentProfile::where('user_id', $request->user()->id)->first();
        $application = Application::where('id', $id)
            ->where('student_profile_id', $profile?->id)
            ->firstOrFail();

        return response()->json($application);
    }

    public function update(Request $request, int $id)
    {
        $profile = StudentProfile::where('user_id', $request->user()->id)->first();
        $application = Application::where('id', $id)
            ->where('student_profile_id', $profile?->id)
            ->firstOrFail();

        if (! in_array($application->status, ['draft', 'pending_revision'])) {
            return response()->json([
                'message' => 'Application can only be edited when in draft or pending revision status.',
            ], 422);
        }

        $data = $request->validate([
            'applicant_type' => 'sometimes|in:student,team',
            'program_type' => 'sometimes|in:a,b',
            'team_id' => 'nullable|exists:teams,id',
            'internal_notes' => 'sometimes|string',
        ]);

        $application->update($data);

        return response()->json($application);
    }

    public function destroy(Request $request, int $id)
    {
        $profile = StudentProfile::where('user_id', $request->user()->id)->first();
        $application = Application::where('id', $id)
            ->where('student_profile_id', $profile?->id)
            ->firstOrFail();

        if (! in_array($application->status, ['draft', 'pending_revision'])) {
            return response()->json([
                'message' => 'Only draft or pending revision applications can be deleted.',
            ], 422);
        }

        DB::transaction(function () use ($application, $id) {
            
            if ($application->applicant_type === 'team' && $application->team_id) {
                $team = \App\Models\Team::find($application->team_id);
                if ($team) {
                    $team->update(['status' => 'forming']);
                }
            }

            $docs = DB::table('application_documents')
                ->join('documents', 'documents.id', '=', 'application_documents.document_id')
                ->where('application_documents.application_id', $id)
                ->select('documents.id', 'documents.file_path')
                ->get();

            foreach ($docs as $doc) {
                Storage::disk('local')->delete($doc->file_path);
                DB::table('application_documents')->where('document_id', $doc->id)->delete();
                Document::find($doc->id)?->delete();
            }

            $application->delete();
        });

        return response()->json(['message' => 'Application deleted']);
    }

    public function documents(Request $request, int $id)
    {
        $profile = StudentProfile::where('user_id', $request->user()->id)->first();
        $application = Application::where('id', $id)
            ->where('student_profile_id', $profile?->id)
            ->firstOrFail();

        $docs = DB::table('application_documents')
            ->join('documents', 'documents.id', '=', 'application_documents.document_id')
            ->where('application_documents.application_id', $id)
            ->select('documents.id', 'documents.type', 'documents.file_name', 'documents.created_at')
            ->get();

        return response()->json($docs);
    }

    public function adminIndex(Request $request)
    {
        $query = Application::with(['call', 'studentProfile.user', 'team']);

        if ($request->has('call_id') && is_numeric($request->call_id)) {
            $query->where('call_id', $request->call_id);
        }

        $applications = $query->orderByDesc('created_at')->get()->map(function ($app) {
            return [
                'id' => $app->id,
                'call_id' => $app->call_id,
                'call_name' => $app->call ? $app->call->name : 'Neznáma výzva',
                'applicant_name' => ($app->studentProfile && $app->studentProfile->user) ? $app->studentProfile->user->name : 'N/A',
                'team_name' => $app->team ? $app->team->name : 'Jednotlivec',
                'status' => $app->status,
                'submitted_at' => $app->created_at->format('Y-m-d'),
            ];
        });

        return response()->json($applications);
    }

    public function adminUpdateStatus(Request $request, int $id)
    {
        $data = $request->validate([
            'status' => 'required|in:draft,submitted,formal_check,formally_verified,evaluation,under_evaluation,pending_revision,approved,rejected,needs_info,onboarding,active,suspended,closed',
        ]);

        $application = Application::findOrFail($id);
        $oldStatus = $application->status;
        $application->update(['status' => $data['status']]);
        $applicantUser = $application->studentProfile->user ?? null;

        if ($applicantUser) {
            NotificationController::log($applicantUser->id, $applicantUser->email, 'status_changed_by_admin',
                'Status vašej prihlášky #'.$application->id.' bol zmenený z '.$oldStatus.' na '.$data['status'].'.',
                ['application_id' => $application->id, 'old_status' => $oldStatus, 'new_status' => $data['status']]
            );
        }

        return response()->json(['status' => $application->status]);
    }
}