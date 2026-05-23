<?php

namespace App\Services;

use App\Models\Audit;
use Illuminate\Support\Facades\Request;

class AuditService
{
    /**
     * Log an admin action to the audit table.
     * @param  string       $action   approve | block | unblock | assign | remove | delete | create | export
     * @param  string|null  $object   The entity being acted on (e.g. 'users', 'applications', user email, role slug)
     * @param  array|null   $details  Extra context — sensitive keys are stripped automatically
     * @param  int|null  $userId   The ID of the user performing the action (defaults to currently authenticated user)
     */
    public static function log(
        string $action,
        ?string $object = null,
        ?array $details = null,
        ?int $userId = null
    ): void {
        $sensitiveKeys = ['password', 'password_confirmation', 'token', 'admin_email'];

        if ($details !== null) {
            $details = array_diff_key(
                $details,
                array_flip($sensitiveKeys)
            );

            if (isset($details['filters']) && is_array($details['filters'])) {
                $details['filters'] = array_diff_key(
                    $details['filters'],
                    array_flip($sensitiveKeys)
                );
            }
        }

        Audit::create([
            'user_id' => $userId ?? auth()->id(),
            'action' => $action,
            'object' => $object,
            'details' => $details,
            'ip_address' => Request::ip(),
            'created_at' => now(),
        ]);
    }
}