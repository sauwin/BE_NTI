<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreApplicationRequest;
use App\Models\Application;
use App\Models\StudentProfile;
use App\Models\ApplicationRevisionRequest;
use App\Services\AdminApplicationService;
use App\Services\ApplicationService;
use Illuminate\Http\Request;

/**
 * @tags Application Management
 * Endpoints for creating, managing, updating statuses, and processing user program applications and related documentation.
 */
class ApplicationController extends Controller
{
    protected $applicationService;

    protected $adminService;

    public function __construct(ApplicationService $applicationService, AdminApplicationService $adminService)
    {
        $this->applicationService = $applicationService;
        $this->adminService = $adminService;
    }

    /**
     * Select all applications for admins and application owned by student
     */
    public function index(Request $request)
    {
        $user = $request->user();

        if ($user->hasRole(['nti_admin', 'super_admin'])) {
            return response()->json(
                Application::with(['team', 'call'])->orderBy('created_at', 'desc')->get()
            );
        }

        $profile = StudentProfile::where('user_id', $user->id)->first();

        if (! $profile) {
            return response()->json([], 200);
        }

        return response()->json(
            Application::with(['team', 'call'])
                ->where('student_profile_id', $profile->id)
                ->orderBy('created_at', 'desc')
                ->get()
        );
    }

    /**
     * Create new application in db
     */
    public function store(StoreApplicationRequest $request)
    {
        $application = $this->applicationService->createApplication(
            $request->validated(),
            $request->user()
        );

        return response()->json(['application_id' => $application->id], 201);
    }

    /**
     * Select application and the assessment status
     */
    public function show(Request $request, int $id)
    {
        $application = Application::with(['studentProfile.user', 'team', 'call'])
            ->withCount(['evaluations as completed_evaluations_count' => function ($query) {
                $query->where('status', 'completed');
            }])
            ->findOrFail($id);

        $this->authorize('view', $application);

        $totalEvaluators = \App\Models\CallEvaluator::where('call_id', $application->call_id)->count();
        
        $application->total_evaluators_count = $totalEvaluators;
        $application->pending_evaluators_count = max(0, $totalEvaluators - $application->completed_evaluations_count);

        return response()->json($application);
    }

    /**
     * Update application (only draft)
     */
    public function update(Request $request, int $id)
    {
        $application = Application::findOrFail($id);

        if (! in_array($application->status, ['draft', 'pending_revision'])) {
            return response()->json(['message' => 'Only drafts can be edited.'], 422);
        }

        $this->authorize('update', $application);

        $data = $request->validate([
            'category' => 'nullable|string|max:255',
            'team_id' => 'nullable|exists:teams,id',
        ]);

        $application->update($data);

        return response()->json(['message' => 'Application updated successfully', 'application' => $application]);
    }

    /**
     * Submitting an application
     */
    public function applyChanges(Request $request, int $id)
    {
        $application = Application::findOrFail($id);

        $this->applicationService->applyChanges($application, $request->user());

        return response()->json([
            'message' => 'Application submitted successfully',
            'status' => 'submitted',
        ]);
    }

    /**
     * Submitting an application from the draft
     */
    public function submitDraft(Request $request, int $id)
    {
        $application = Application::findOrFail($id);

        $this->applicationService->submitDraft($application, $request->user());

        return response()->json([
            'message' => 'Application submitted successfully',
            'status' => 'submitted',
        ]);
    }

    /**
     * Delete application (only draft)
     */
    public function destroy(Request $request, int $id)
    {
        $application = Application::findOrFail($id);

        if ($application->status !== 'draft') {
            return response()->json(['message' => 'Only drafts can be deleted.'], 422);
        }

        $this->authorize('delete', $application);

        $application->delete();

        return response()->json(['message' => 'Draft deleted successfully']);
    }

    /**
     * Select all documents owned by the application
     */
    public function documents(Request $request, int $id)
    {
        $application = Application::findOrFail($id);

        $this->authorize('view', $application);

        $documents = $application->documents;

        return response()->json($documents);
    }

    /**
     * Update application status (Admin full access / Mentor limited access)
     */
    public function updateStatus(Request $request, int $id)
    {
        $application = Application::findOrFail($id);
        $user = $request->user();

        $this->authorize('updateStatus', $application);

        $isAdmin = $user->hasRole(['nti_admin', 'super_admin']);

        $statusValidationRule = $isAdmin
            ? 'required|in:submitted,formally_verified,under_evaluation,revision_requested,approved,rejected,onboarding,active,suspended,closed'
            : 'required|in:onboarding,active,approved,suspended,closed';

        $request->validate([
            'status' => $statusValidationRule,
            'comment' => 'nullable|string|max:1000',
        ]);

        $this->adminService->updateStatus($application, $request->status, $request->comment, $user);

        return response()->json(['message' => "Application status updated successfully to {$request->status}."]);
    }

    /**
     * Selects last revision request (for student to check comment)
     */
    public function getLastRevisionRequest(Application $application)
    {
        \Log::info($application->id);
        $this->authorize('view', $application);

        return response()->json(
            ApplicationRevisionRequest::where('application_id', $application->id)
                ->latest()
                ->first()
        );
    }
}
