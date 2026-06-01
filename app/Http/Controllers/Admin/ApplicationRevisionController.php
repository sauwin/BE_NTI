<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Application;
use App\Models\ApplicationRevisionRequest;
use App\Models\StudentProfile;
use App\Services\AdminApplicationService;
use Illuminate\Http\Request;

/**
 * @tags Admin Management
 * Endpoints for managing application correction workflows, including initiating formal revision requests via service-layer notification logic and retrieving chronological audit logs of all revision history for both administrators and applicants.
 */
class ApplicationRevisionController extends Controller
{
    protected $adminService;

    public function __construct(AdminApplicationService $adminService)
    {
        $this->adminService = $adminService;
    }

    /**
     * Send revesion request and notified student
     */
    public function requestRevision(Request $request, int $id)
    {
        $request->validate([
            'message' => 'required|string|min:5|max:2000'
        ]);

        $application = Application::findOrFail($id);
        
        $this->adminService->createRevisionRequest($application, $request->message, $request->user());

        return response()->json(['message' => 'Revision request created successfully and student notified.']);
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