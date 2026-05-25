<?php

namespace App\Http\Controllers;

use App\Mail\ApplicationRevisionRequestMail;
use App\Mail\ApplicationSubmittedMail;
use App\Mail\ProjectClosedMail;
use App\Mail\StatusChangedMail;
use App\Models\Application;
use App\Models\ApplicationRevisionRequest;
use App\Models\ApplicationStatusHistory;
use App\Models\Call;
use App\Models\Document;
use App\Models\StudentProfile;
use App\Models\Team;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;

class ApplicationController extends Controller
{
    private function isAdmin(Request $request): bool
    {
        return $request->user()->roles->contains(fn ($r) => in_array($r->slug, ['nti_admin', 'super_admin']));
    }

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
                'message' => 'Team specification is required for team aplication',
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
                'message' => 'Pred odoslaním prihlášky si musíte vytvoriť študentský profil.',
            ], 403);
        }

        $team = null;

        if ($data['applicant_type'] === 'team') {
            $team = Team::withCount(['members' => function ($query) {
                $query->where('team_members.status', 'accepted');
            }])->find($data['team_id']);

            if ($isFinalSubmit && $team->status !== 'forming') {
                return response()->json([
                    'message' => 'Táto prihláška nemôže byť odoslaná, pretože tím už je v stave ready (uzamknutý).',
                ], 422);
            }

            if ($team->leader_id !== $request->user()->id) {
                return response()->json([
                    'message' => 'Iba líder tímu môže podať prihlášku za tento tím.',
                ], 403);
            }

            if ($isFinalSubmit && $team->members_count < 3) {
                return response()->json([
                    'message' => 'Tím mustí mať minimálne 3 členov s potvrdeným statusom (accepted) pre prihlásenie do Programu A.',
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

        $application = DB::transaction(function () use ($call, $data, $profile, $team, $isFinalSubmit, $request) {
            $initialStatus = $isFinalSubmit ? 'submitted' : 'draft';

            $app = Application::create([
                'call_id' => $call->id,
                'applicant_type' => $data['applicant_type'],
                'program_type' => $data['program_type'],
                'team_id' => $data['team_id'] ?? null,
                'student_profile_id' => $profile?->id,
                'status' => $initialStatus,
            ]);

            // Автоматичний запис первинного створення в історію
            ApplicationStatusHistory::create([
                'application_id' => $app->id,
                'old_status' => null,
                'new_status' => $initialStatus,
                'changed_by' => $request->user()->id,
                'comment' => $isFinalSubmit ? 'Prvotné odoslanie prihlášky' : 'Vytvorenie konceptu prihlášky',
                'changed_at' => now(),
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
            'status' => 'required|in:draft,submitted,formally_verified,under_evaluation,pending_revision,approved,rejected,onboarding,active,suspended,closed',
        ]);

        $application = Application::findOrFail($id);
        $oldStatus = $application->status;

        $application->update(['status' => $data['status']]);

        ApplicationStatusHistory::create([
            'application_id' => $application->id,
            'old_status' => $oldStatus,
            'new_status' => $data['status'],
            'changed_by' => $request->user()->id,
            'comment' => 'Zmena statusu v systéme',
            'changed_at' => now(),
        ]);

        $applicantUser = $application->studentProfile->user ?? $request->user();

        if ($data['status'] === 'closed') {
            Mail::to($applicantUser->email)->queue(new ProjectClosedMail($applicantUser, $application));
            NotificationController::log($applicantUser->id, $applicantUser->email, 'project_closed',
                'Your project #'.$application->id.' has been closed.',
                ['application_id' => $application->id]
            );
        } else {
            Mail::to($applicantUser->email)->queue(new StatusChangedMail($applicantUser, $application, $oldStatus));
            NotificationController::log($applicantUser->id, $applicantUser->email, 'status_changed',
                'Your application #'.$application->id.' status changed from '.$oldStatus.' to '.$data['status'].'.',
                ['application_id' => $application->id, 'old_status' => $oldStatus, 'new_status' => $data['status']]
            );
        }

        return response()->json(['status' => $application->status]);
    }

    public function index(Request $request)
    {
        $user = $request->user();
        $isAdmin = $user->roles->contains(fn ($r) => in_array($r->slug, ['nti_admin', 'super_admin']));

        if ($isAdmin) {
            return response()->json(Application::orderByDesc('created_at')->get());
        }

        $profile = $user->studentProfile;
        if (! $profile) {
            return response()->json([]);
        }

        return response()->json(
            Application::where('student_profile_id', $profile->id)->orderByDesc('created_at')->get()
        );
    }

    public function show(Request $request, int $id)
    {
        if ($this->isAdmin($request)) {
            $application = Application::findOrFail($id);
            $this->authorize('view', $application);

            return response()->json($application);
        }
        $profile = StudentProfile::where('user_id', $request->user()->id)->first();
        $application = Application::where('id', $id)
            ->where('student_profile_id', $profile?->id)
            ->firstOrFail();

        return response()->json($application);
    }

    public function update(Request $request, int $id)
    {
        if ($this->isAdmin($request)) {
            $application = Application::findOrFail($id);
            $this->authorize('update', $application);
        } else {
            $profile = StudentProfile::where('user_id', $request->user()->id)->first();
            $application = Application::where('id', $id)
                ->where('student_profile_id', $profile?->id)
                ->firstOrFail();
        }

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
        if ($this->isAdmin($request)) {
            $application = Application::findOrFail($id);
            $this->authorize('delete', $application);
        } else {
            $profile = StudentProfile::where('user_id', $request->user()->id)->first();
            $application = Application::where('id', $id)
                ->where('student_profile_id', $profile?->id)
                ->firstOrFail();
        }

        if (! in_array($application->status, ['draft', 'pending_revision'])) {
            return response()->json([
                'message' => 'Only draft or pending revision applications can be deleted.',
            ], 422);
        }

        DB::transaction(function () use ($application, $id) {

            if ($application->applicant_type === 'team' && $application->team_id) {
                $team = Team::find($application->team_id);
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
        if ($this->isAdmin($request)) {
            $application = Application::findOrFail($id);
            $this->authorize('view', $application);
        } else {
            $profile = StudentProfile::where('user_id', $request->user()->id)->first();
            $application = Application::where('id', $id)
                ->where('student_profile_id', $profile?->id)
                ->firstOrFail();
        }

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
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('program_type')) {
            $query->where('program_type', $request->program_type);
        }

        if ($request->filled('call_id') && is_numeric($request->call_id)) {
            $query->where('call_id', $request->call_id);
        }

        if ($request->filled('search')) {
            $search = trim($request->search);
            $query->whereHas('studentProfile.user', function ($q) use ($search) {
                $q->where('first_name', 'like', '%'.$search.'%')
                    ->orWhere('last_name', 'like', '%'.$search.'%')
                    ->orWhere('email', 'like', '%'.$search.'%');
            });
        }

        $paginatedApplications = $query->orderByDesc('created_at')->paginate(20);

        $paginatedApplications->getCollection()->transform(function ($app) {
            $name = 'N/A';
            if ($app->studentProfile && $app->studentProfile->user) {
                $name = trim($app->studentProfile->user->first_name.' '.$app->studentProfile->user->last_name);
            }

            return [
                'id' => $app->id,
                'call_id' => $app->call_id,
                'call_name' => $app->call ? $app->call->name : 'Neznáma výzva',
                'applicant_name' => $name,
                'applicant_email' => ($app->studentProfile && $app->studentProfile->user) ? $app->studentProfile->user->email : 'N/A',
                'program_type' => strtoupper($app->program_type),
                'team_name' => $app->team ? $app->team->name : 'Jednotlivec',
                'status' => $app->status,
                'submitted_at' => $app->created_at->format('Y-m-d H:i:s'),
            ];
        });

        return response()->json($paginatedApplications);
    }

    public function adminShow(Request $request, int $id)
    {
        $application = Application::with([
            'call',
            'studentProfile.user',
            'team.members.user',
            'evaluations.evaluator',
        ])->findOrFail($id);

        $documents = DB::table('application_documents')
            ->join('documents', 'documents.id', '=', 'application_documents.document_id')
            ->where('application_documents.application_id', $id)
            ->select('documents.id', 'documents.type', 'documents.file_name', 'documents.mime_type', 'documents.file_size_bytes', 'documents.created_at')
            ->get();

        return response()->json([
            'application' => $application,
            'documents' => $documents,
        ]);
    }

    public function adminUpdateStatus(Request $request, int $id)
    {
        return $this->updateStatus($request, $id);
    }

    public function getHistory(Request $request, int $id)
    {
        $application = Application::findOrFail($id);

        if ($request->user()->roles->contains('slug', 'student')) {
            $profile = StudentProfile::where('user_id', $request->user()->id)->first();
            if (! $profile || $application->student_profile_id !== $profile->id) {
                return response()->json(['message' => 'Unauthorized'], 403);
            }
        }

        $history = ApplicationStatusHistory::with('user')
            ->where('application_id', $id)
            ->orderBy('changed_at', 'desc')
            ->get()
            ->map(function ($item) {
                $userName = 'Systém';
                if ($item->user) {
                    $userName = trim(($item->user->first_name ?? '').' '.($item->user->last_name ?? ''));
                    if (empty($userName)) {
                        $userName = $item->user->email;
                    }
                }

                return [
                    'timestamp' => $item->changed_at->toIso8601String(),
                    'old_status' => $item->old_status,
                    'new_status' => $item->new_status,
                    'changed_by' => $userName,
                ];
            });

        return response()->json($history);
    }

    public function createRevisionRequest(Request $request, int $id)
    {
        $data = $request->validate([
            'message' => 'required|string|max:5000',
            'required_fields' => 'nullable|array',
        ]);

        $application = Application::findOrFail($id);
        $oldStatus = $application->status;

        DB::transaction(function () use ($application, $oldStatus, $data, $request) {
            $application->update(['status' => 'pending_revision']);

            ApplicationStatusHistory::create([
                'application_id' => $application->id,
                'old_status' => $oldStatus,
                'new_status' => 'pending_revision',
                'changed_by' => $request->user()->id,
                'comment' => 'Vyžiadaná revízia aplikácie administrátorom.',
                'changed_at' => now(),
            ]);

            $finalMessage = $data['message'];
            if (! empty($data['required_fields'])) {
                $finalMessage .= "\n\nPožadované polia na úpravu:\n- ".implode("\n- ", $data['required_fields']);
            }

            ApplicationRevisionRequest::create([
                'application_id' => $application->id,
                'requested_by' => $request->user()->id,
                'message' => $finalMessage,
                'deadline' => now()->addDays(7),
                'created_at' => now(),
            ]);

            $applicantUser = $application->studentProfile->user ?? null;
            if ($applicantUser) {
                try {
                    Mail::to($applicantUser->email)->queue(
                        new ApplicationRevisionRequestMail($application, $finalMessage)
                    );
                } catch (\Exception $e) {
                }

                NotificationController::log($applicantUser->id, $applicantUser->email, 'revision_requested',
                    'Administrátor vyžaduje úpravu vašej prihlášky #'.$application->id,
                    ['application_id' => $application->id]
                );
            }
        });

        return response()->json(['message' => 'Revision request created successfully and student notified.']);
    }

    public function getRevisionRequest(Request $request, int $id)
    {
        $application = Application::findOrFail($id);

        if ($request->user()->roles->contains('slug', 'student')) {
            $profile = StudentProfile::where('user_id', $request->user()->id)->first();
            if (! $profile || $application->student_profile_id !== $profile->id) {
                return response()->json(['message' => 'Unauthorized'], 403);
            }
        }

        $requests = ApplicationRevisionRequest::where('application_id', $id)
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json($requests);
    }
}
