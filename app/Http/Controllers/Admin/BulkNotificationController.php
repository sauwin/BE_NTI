<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Jobs\SendBulkNotification;
use App\Models\User;
use Illuminate\Http\Request;

class BulkNotificationController extends Controller
{
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

        foreach ($users as $user) {
            SendBulkNotification::dispatch(
                $user->id,
                $user->email,
                $data['subject'],
                $data['message'],
                $data['recipient_group'],
            );
        }

        return response()->json(['queued' => $users->count()]);
    }

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
