<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Jobs\SendBulkNotification;
use App\Models\User;
use App\Models\BulkNotificationCampaign;
use Illuminate\Http\Request;
use App\Services\AuditService;

class BulkNotificationController extends Controller
{
    /**
     * Seelct history for Bulk Notification
     */
    public function history()
    {
        $history = BulkNotificationCampaign::with('sender:id,first_name,last_name,email')
            ->latest()
            ->get();
        return response()->json($history);
    }

    /**
     * Send the Bulk Notification
     */
    public function send(Request $request)
    {
        $data = $request->validate([
            'recipient_group' => [
                'required',
                'string',
                function ($attr, $value, $fail) {
                    $valid = ['all', 'students', 'companies', 'mentors'];
                    if (in_array($value, $valid)) {
                        return;
                    }
                    if (preg_match('/^call_\d+$/', $value)) {
                        return;
                    }
                    $fail('Invalid recipient_group.');
                },
            ],
            'subject' => 'required|string|max:255',
            'message' => 'required|string|max:10000',
        ]);

        $users = $this->resolveRecipients($data['recipient_group']);
        $totalRecipients = $users->count();

        BulkNotificationCampaign::create([
            'recipient_group' => $data['recipient_group'],
            'subject' => $data['subject'],
            'message' => $data['message'],
            'total_recipients' => $totalRecipients,
            'sender_id' => auth()->id(),
        ]);

        AuditService::log('bulk_notification', 'notification', [
            'recipient_group' => $data['recipient_group'],
            'subject' => $data['subject'],
            'total_recipients' => $totalRecipients,
        ]);

        foreach ($users as $user) {
            SendBulkNotification::dispatch(
                $user->id,
                $user->email,
                $data['subject'],
                $data['message'],
                $data['recipient_group'],
            );
        }

        return response()->json(['queued' => $totalRecipients]);
    }

    /**
     * Select a group by role
     */
    private function resolveRecipients(string $group)
    {
        $query = User::query()->where('status', 'active');

        if ($group === 'all') {
            return $query->get(['id', 'email']);
        }
        if ($group === 'students') {
            return $query->whereHas('roles', fn ($q) => $q->where('slug', 'student'))->get(['id', 'email']);
        }
        if ($group === 'companies') {
            return $query->whereHas('roles', fn ($q) => $q->where('slug', 'company'))->get(['id', 'email']);
        }
        if ($group === 'mentors') {
            return $query->whereHas('roles', fn ($q) => $q->where('slug', 'mentor'))->get(['id', 'email']);
        }

        if (preg_match('/^call_(\d+)$/', $group, $m)) {
            $callId = (int) $m[1];

            return $query->whereHas('studentProfile', function ($q) use ($callId) {
                $q->whereHas('applications', fn ($sq) => $sq->where('call_id', $callId));
            })->get(['id', 'email']);
        }

        return collect();
    }
}