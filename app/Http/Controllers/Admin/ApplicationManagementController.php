<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Application;
use App\Models\ApplicationRevisionRequest;
use App\Services\ApplicationWorkflowService;
use Illuminate\Http\Request;

/**
 * @tags Admin Management
 * Endpoints for administrative oversight of student applications, providing paginated access to submission data including profile details, team associations, and program call context, with filtering capabilities by status and program type.
 */
class ApplicationManagementController extends Controller
{
    protected $applicationWorkflowService;

    public function __construct(ApplicationWorkflowService $applicationWorkflowService)
    {
        $this->applicationWorkflowService = $applicationWorkflowService;
    }

    /**
     * Select all application for admin dashboard
     */
    public function index(Request $request)
    {
        $this->authorize('viewAdminDashboard', Application::class);

        $applications = Application::with(['studentProfile.user', 'team', 'call'])
            ->when($request->status, fn($q, $status) => $q->where('status', $status))
            ->when($request->program, fn($q, $program) => $q->where('program_type', $program))
            ->orderBy('created_at', 'desc')
            ->paginate($request->input('per_page', 20));

        return response()->json($applications);
    }

    /**
     * Select history for revision requests
     */
    public function getRevisionHistory(Application $application)
    {
        $this->authorize('view', $application);

        return response()->json(
            ApplicationRevisionRequest::where('application_id', $application->id)
                ->latest()
                ->get()
        );
    }
}