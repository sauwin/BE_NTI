<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ReportingController extends Controller
{
    /**
     * Reporting stats for admin dashboard
     */
    public function dashboardStats(Request $request)
    {
        abort_unless(
            $request->user()?->roles()->whereIn('slug', ['nti_admin', 'super_admin'])->exists(),
            403,
            'Unauthorized. Admin required.'
        );

        $users = DB::table('users')
            ->selectRaw('1 as total_users, 0 as active_projects, 0 as pending_approvals, 0 as open_calls');

        $applications = DB::table('applications')
            ->selectRaw('0 as total_users, CASE WHEN status = ? THEN 1 ELSE 0 END as active_projects, 0 as pending_approvals, 0 as open_calls', ['active']);

        $pendingApprovals = DB::table('user_roles')
            ->selectRaw('0 as total_users, 0 as active_projects, CASE WHEN granted_by IS NULL THEN 1 ELSE 0 END as pending_approvals, 0 as open_calls');

        $calls = DB::table('calls')
            ->selectRaw('0 as total_users, 0 as active_projects, 0 as pending_approvals, CASE WHEN status = ? THEN 1 ELSE 0 END as open_calls', ['open']);

        $unionQuery = $users->unionAll($applications)->unionAll($pendingApprovals)->unionAll($calls);

        $metrics = DB::query()
            ->fromSub($unionQuery, 'metric_rows')
            ->selectRaw('COUNT(CASE WHEN total_users = 1 THEN 1 END) as total_users')
            ->selectRaw('COUNT(CASE WHEN active_projects = 1 THEN 1 END) as active_projects')
            ->selectRaw('COUNT(CASE WHEN pending_approvals = 1 THEN 1 END) as pending_approvals')
            ->selectRaw('COUNT(CASE WHEN open_calls = 1 THEN 1 END) as open_calls')
            ->first();

        return response()->json([
            'total_users' => (int) $metrics->total_users,
            'active_projects' => (int) $metrics->active_projects,
            'pending_approvals' => (int) $metrics->pending_approvals,
            'open_calls' => (int) $metrics->open_calls,
        ]);
    }
}
