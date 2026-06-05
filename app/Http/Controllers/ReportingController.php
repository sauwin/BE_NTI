<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Call;
use App\Models\Application;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/**
 * @tags System Reporting
 * Endpoints for aggregating high-level system analytics, including user role distribution, operational call statuses, and application lifecycle metrics for administrative dashboard visualization.
 */
class ReportingController extends Controller
{
    /**
     * Reporting for dashboard stats
     */
    public function dashboardStats(Request $request)
    {
        Gate::authorize('viewDashboardStats', 'reporting');

        $totalUsers = User::count();

        $students = User::whereHas('roles', function($q) { 
            $q->where('slug', 'student'); 
        })->count();
        $admins = User::whereHas('roles', function($q) { 
            $q->where('slug', 'nti_admin'); 
        })->count();
        $contentEditors = User::whereHas('roles', function($q) { 
            $q->where('slug', 'content_editor'); 
        })->count();
        $evaluators = User::whereHas('roles', function($q) { 
            $q->where('slug', 'evaluator'); 
        })->count();
        $mentors = User::whereHas('roles', function($q) { 
            $q->where('slug', 'mentor'); 
        })->count();

        $companyOwners = User::where('role_in_org', 'owner')
            ->whereHas('roles', function($q) { 
                $q->where('slug', 'company'); 
            })->count();

        $totalCalls = Call::count();
        $openCalls = Call::where('status', 'open')->count();

        $totalApplications = Application::count();
        $appSubmitted = Application::where('status', 'submitted')->count();
        $appActive = Application::where('status', 'active')->count();
        $appClosed = Application::where('status', 'closed')->count();

        $data = [
            'total_users' => $totalUsers,
            'students' => $students,
            'company_owners' => $companyOwners,
            'admins' => $admins,
            'content_editors' => $contentEditors,
            'evaluators' => $evaluators,
            'mentors' => $mentors,
            'total_calls' => $totalCalls,
            'open_calls' => $openCalls,
            'total_applications' => $totalApplications,
            'application_submitted'=> $appSubmitted,
            'application_active' => $appActive,
            'application_closed' => $appClosed,
        ];

        return response()->json($data);
    }
}