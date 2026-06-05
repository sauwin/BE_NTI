<?php

namespace App\Services;

use App\Http\Controllers\NotificationController;
use App\Mail\ApplicationSubmittedMail;
use App\Models\Application;
use App\Models\ApplicationStatusHistory;
use App\Models\Call;
use App\Models\StudentProfile;
use App\Models\Team;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;
use App\Mail\ApplicationRevisionSubmittedMail;

class ApplicationService
{
    public function createApplication(array $data, $user): Application
    {
        $isFinalSubmit = ($data['submit_type'] ?? 'final') === 'final';

        $call = Call::where('program', $data['program_type'])
            ->where('status', 'open')
            ->latest()
            ->first();

        if (! $call) {
            throw ValidationException::withMessages(['program_type' => 'No active call found for this program.']);
        }

        $profile = StudentProfile::where('user_id', $user->id)->first();

        $team = null;

        if ($data['applicant_type'] === 'team') {
            $team = Team::withCount(['members' => function ($query) {
                $query->where('team_members.status', 'accepted');
            }])->find($data['team_id']);

            if ($isFinalSubmit && $team->status !== 'forming') {
                throw ValidationException::withMessages([
                    'team_id' => 'Táto prihláška nemôže byť odoslaná, pretože tím už je v stave ready (uzamknutý).',
                ]);
            }

            Gate::authorize('update', $team);

            if ($isFinalSubmit && $team->members_count < 3) {
                throw ValidationException::withMessages([
                    'team_id' => 'Tím mustí mať minimálne 3 členov s potvrdeným statusom (accepted) pre prihlásenie do Programu A.',
                ]);
            }
        }

        if ($isFinalSubmit && $data['program_type'] === 'a' && empty($data['category'])) {
            throw ValidationException::withMessages(['category' => 'Focus category is required for Program A submission.']);
        }

        $maxApps = (int) env('MAX_ACTIVE_APPLICATIONS', 0);
        if ($maxApps > 0 && $isFinalSubmit) {
            $activeCount = Application::where('student_profile_id', $profile->id)
                ->whereNotIn('status', ['rejected', 'closed', 'draft'])
                ->count();

            if ($activeCount >= $maxApps) {
                throw ValidationException::withMessages([
                    'limit' => 'You have reached the maximum number of active applications.',
                ]);
            }
        }

        return DB::transaction(function () use ($call, $profile, $team, $isFinalSubmit, $data, $user) {
            $initialStatus = $isFinalSubmit ? 'submitted' : 'draft';

            $app = Application::create([
                'call_id' => $call->id,
                'applicant_type' => $data['applicant_type'],
                'program_type' => $data['program_type'],
                'team_id' => $data['team_id'] ?? null,
                'student_profile_id' => $profile->id,
                'status' => $initialStatus,
                'category' => $data['category'] ?? null,
            ]);

            ApplicationStatusHistory::create([
                'application_id' => $app->id,
                'old_status' => null,
                'new_status' => $initialStatus,
                'changed_by' => $user->id,
                'comment' => $isFinalSubmit ? 'Prvotné odoslanie prihlášky' : 'Vytvorenie konceptu prihlášky',
                'changed_at' => now(),
            ]);

            if ($team && $isFinalSubmit) {
                $team->update(['status' => 'ready']);
            }

            if ($isFinalSubmit) {
                $this->sendSubmissionNotifications($app, $user);
            }

            return $app;
        });
    }

    public function submitDraft(Application $application, $user): void
    {
        if ($application->applicant_type === 'team' && $application->team) {
            $team = Team::withCount(['members' => function ($query) {
                $query->where('team_members.status', 'accepted');
            }])->find($application->team_id);

            Gate::authorize('update', $team);

            if ($application->program_type === 'a' && $team->members_count < 3) {
                throw ValidationException::withMessages([
                    'team_id' => 'Tím musí mať minimálne 3 členov s potvrdeným statusom (accepted) pre Program A.',
                ]);
            }
        }

        $this->validateRequiredDocuments($application);

        DB::transaction(function () use ($application, $user) {
            $oldStatus = $application->status;
            $newStatus = 'submitted';

            $application->update(['status' => $newStatus]);

            ApplicationStatusHistory::create([
                'application_id' => $application->id,
                'old_status' => $oldStatus,
                'new_status' => $newStatus,
                'changed_by' => $user->id,
                'comment' => 'Odoslanie uloženej чернетки (draft -> submitted)',
                'changed_at' => now(),
            ]);

            if ($application->applicant_type === 'team' && $application->team) {
                $application->team->update(['status' => 'ready']);
            }
        });

        $this->sendSubmissionNotifications($application, $user);
    }

    public function applyChanges(Application $application, $user): void
    {
        $this->validateRequiredDocuments($application);

        DB::transaction(function () use ($application, $user) {
            $oldStatus = $application->status;
            $newStatus = 'under_evaluation'; 

            $application->update(['status' => $newStatus]);

            ApplicationStatusHistory::create([
                'application_id' => $application->id,
                'old_status' => $oldStatus,
                'new_status' => $newStatus,
                'changed_by' => $user->id,
                'comment' => 'Opätovné podanie prihlášky po doplnení chýbajúcich údajov (pending_revision -> submitted).',
                'changed_at' => now(),
            ]);
        });

        $this->sendRevisionSubmissionNotifications($application, $user);
    }

    private function validateRequiredDocuments(Application $application): void
    {
        $call = Call::find($application->call_id);
        if ($call && is_array($call->required_documents)) {
            $uploadedTypes = DB::table('application_documents')
                ->join('documents', 'documents.id', '=', 'application_documents.document_id')
                ->where('application_documents.application_id', $application->id)
                ->pluck('documents.type')
                ->toArray();

            foreach ($call->required_documents as $reqDoc) {
                $docName = is_string($reqDoc) ? $reqDoc : ($reqDoc['document_name'] ?? $reqDoc['type'] ?? '');
                $docTypeKey = Str::snake(trim($docName));

                if (! in_array($docTypeKey, $uploadedTypes)) {
                    throw ValidationException::withMessages([
                        'documents' => 'Chýba povinný dokument: '.(is_string($reqDoc) ? $reqDoc : ($reqDoc['document_name'] ?? $docTypeKey)),
                    ]);
                }
            }
        }
    }

    private function sendSubmissionNotifications(Application $application, $user): void
    {
        try {
            Mail::to($user->email)->queue(new ApplicationSubmittedMail($user, $application));
            NotificationController::log($user->id, $user->email, 'application_submitted',
                'Your application #'.$application->id.' for Program '.strtoupper($application->program_type).' was submitted.',
                ['application_id' => $application->id]
            );
        } catch (\Exception $e) {
            logger()->error('Failed sending email notifications: '.$e->getMessage());
        }
    }

    private function sendRevisionSubmissionNotifications(Application $application, $user): void
    {
        try {
            Mail::to($user->email)->queue(new ApplicationRevisionSubmittedMail($user, $application));

            NotificationController::log(
                $user->id, 
                $user->email, 
                'application_revision_submitted',
                'Zmeny v prihláške #' . $application->id . ' (Program ' . strtoupper($application->program_type) . ') boli úspešne uložené і prihláška bola znova odoslaná на kontrolu.',
                ['application_id' => $application->id]
            );
        } catch (\Exception $e) {
            logger()->error('Failed sending revision email notifications: ' . $e->getMessage());
        }
    }
}
