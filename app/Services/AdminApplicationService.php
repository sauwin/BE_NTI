<?php

namespace App\Services;

use App\Models\Application;
use App\Models\ApplicationStatusHistory;
use App\Models\ApplicationRevisionRequest;
use App\Mail\StatusChangedMail;
use App\Mail\ProjectClosedMail;
use App\Mail\ApplicationRevisionRequestMail;
use App\Http\Controllers\NotificationController;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

class AdminApplicationService
{
    public function updateStatus(Application $application, string $newStatus, ?string $comment, $admin): void
    {
        $oldStatus = $application->status;

        DB::transaction(function () use ($application, $oldStatus, $newStatus, $comment, $admin) {
            $application->update(['status' => $newStatus]);

            ApplicationStatusHistory::create([
                'application_id' => $application->id,
                'old_status' => $oldStatus,
                'new_status' => $newStatus,
                'changed_by' => $admin->id,
                'comment' => $comment ?? 'Status updated by administrator',
                'changed_at' => now(),
            ]);
        });

        $this->sendAdminStatusNotifications($application, $oldStatus, $newStatus);
    }

    public function createRevisionRequest(Application $application, string $message, $admin): void
    {
        DB::transaction(function () use ($application, $message, $admin) {
            $oldStatus = $application->status;
            $newStatus = 'pending_revision';

            $application->update(['status' => $newStatus]);

            ApplicationStatusHistory::create([
                'application_id' => $application->id,
                'old_status' => $oldStatus,
                'new_status' => $newStatus,
                'changed_by' => $admin->id,
                'comment' => 'Revision requested: ' . $message,
                'changed_at' => now(),
            ]);

            ApplicationRevisionRequest::create([
                'application_id' => $application->id,
                'requested_by' => $admin->id,
                'message' => $message,
                'status' => 'pending',
            ]);

            $applicantUser = $application->studentProfile->user ?? null;
            if ($applicantUser) {
                try {
                    Mail::to($applicantUser->email)->queue(new ApplicationRevisionRequestMail($application, $message));
                } catch (\Exception $e) {}

                NotificationController::log($applicantUser->id, $applicantUser->email, 'revision_requested',
                    'Administrátor vyžaduje úpravu vašej prihlášky #' . $application->id,
                    ['application_id' => $application->id]
                );
            }
        });
    }

    private function sendAdminStatusNotifications(Application $application, string $oldStatus, string $newStatus): void
    {
        $applicantUser = $application->studentProfile->user ?? null;
        if (!$applicantUser) return;

        try {
            if ($newStatus === 'closed') {
                Mail::to($applicantUser->email)->queue(new ProjectClosedMail($applicantUser, $application));
            } else {
                Mail::to($applicantUser->email)->queue(new StatusChangedMail($applicantUser, $application, $oldStatus, $newStatus));
            }

            NotificationController::log($applicantUser->id, $applicantUser->email, 'status_changed',
                "Status of your application #{$application->id} changed to " . strtoupper($newStatus),
                ['application_id' => $application->id, 'old_status' => $oldStatus, 'new_status' => $newStatus]
            );
        } catch (\Exception $e) {
            logger()->error("Admin notification failure: " . $e->getMessage());
        }
    }
}