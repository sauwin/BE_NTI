<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * @tags User Notification
 * Endpoints for retrieving user-specific activity feeds, managing notification status transitions (unread/read), and performing system-wide event logging.
 */
class NotificationController extends Controller
{
    /**
     * Select all read and unread notifications for user
     */
    public function index(Request $request)
    {
        $notifications = DB::table('notification_log')
            ->where('user_id', $request->user()->id)
            ->orderByDesc('created_at')
            ->limit(50)
            ->get();

        $unread = Cache::remember("notifications.unread.{$request->user()->id}", 60, function () use ($request) {
            return DB::table('notification_log')
                ->where('user_id', $request->user()->id)
                ->where('status', 'queued')
                ->count();
        });

        return response()->json([
            'notifications' => $notifications,
            'unread_count' => $unread,
        ]);
    }

    /**
     * Mark "read" for notifications
     */
    public function markRead(Request $request, int $id)
    {
        DB::table('notification_log')
            ->where('id', $id)
            ->where('user_id', $request->user()->id)
            ->update(['status' => 'sent']);

        return response()->json(['message' => 'Marked as read']);
    }

    /**
     * Mark "all read" for notification
     */
    public function markAllRead(Request $request)
    {
        DB::table('notification_log')
            ->where('user_id', $request->user()->id)
            ->where('status', 'queued')
            ->update(['status' => 'sent']);

        Cache::forget("notifications.unread.{$request->user()->id}");

        return response()->json(['message' => 'All marked as read']);
    }

    /**
     * Save log for notification
     */
    public static function log(int $userId, string $recipientEmail, string $eventType, string $message, array $context = []): void
    {
        DB::table('notification_log')->insert([
            'user_id' => $userId,
            'channel' => 'email',
            'recipient_email' => $recipientEmail,
            'status' => 'queued',
            'context' => json_encode(array_merge($context, [
                'event_type' => $eventType,
                'message' => $message,
            ])),
            'sent_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
