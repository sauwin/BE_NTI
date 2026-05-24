<?php

namespace App\Console\Commands;

use App\Mail\DeadlineReminderMail;
use App\Mail\MilestoneDeadlineReminderMail;
use App\Models\Application;
use App\Models\Milestone;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

class SendDeadlineReminders extends Command
{
    protected $signature = 'nti:deadlineReminders';

    protected $description = 'Send deadline reminder emails to applicants';

    public function handle(): void
    {
        // Find calls closing in 7 days
        $calls = DB::table('calls')
            ->where('status', 'open')
            ->whereBetween('deadline_at', [now(), now()->addDays(7)])
            ->get();

        foreach ($calls as $call) {
            $applications = Application::where('call_id', $call->id)
                ->whereIn('status', ['draft', 'submitted'])
                ->get();

            foreach ($applications as $application) {
                $user = User::find($application->student_profile_id ?? $application->created_by ?? null);
                if (! $user) {
                    continue;
                }

                Mail::to($user->email)->queue(new DeadlineReminderMail($user, $call));
            }
        }

        $milestones = Milestone::with('application.studentProfile.user')
            ->whereIn('status', ['pending', 'in_progress'])
            ->whereBetween('due_date', [now()->toDateString(), now()->addDays(7)->toDateString()])
            ->get();

        foreach ($milestones as $milestone) {
            $recipientUser = $milestone->application->studentProfile->user ?? null;
            if (! $recipientUser) {
                continue;
            }
            Mail::to($recipientUser->email)->queue(new MilestoneDeadlineReminderMail($recipientUser, $milestone));
        }

        $this->info('Deadline reminders sent.');
    }
}
