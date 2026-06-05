<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreApplicationRequest;
use App\Models\Application;
use App\Models\StudentProfile;
use App\Models\ApplicationRevisionRequest;
use App\Models\CallEvaluator;
use App\Services\ApplicationWorkflowService;
use App\Services\ApplicationService;
use Illuminate\Http\Request;

/**
 * @tags Application Management
 * Endpoints for creating, managing, updating statuses, and processing user program applications and related documentation.
 */
class ApplicationController extends Controller
{
    protected $applicationService;
    protected $applicationWorkflowService;

    public function __construct(ApplicationService $applicationService, ApplicationWorkflowService $applicationWorkflowService)
    {
        $this->applicationService = $applicationService;
        $this->applicationWorkflowService = $applicationWorkflowService;
    }

    /**
     * Returns information about all evaluations of application
     */
    public function getEvaluations(Request $request, Application $application)
    {
        $this->authorize('view', $application);

        return response()->json($application->evaluations()
            ->with('scores', 'scores.criterion', 'evaluator')
            ->get());
    }

    /**
     * Select all applications for admins and application owned by student
     */
    public function index(Request $request)
    {
        $this->authorize('viewAny', Application::class);

        $user = $request->user();

        return Application::query()
            ->visibleTo($user)
            ->with(['team', 'call'])
            ->latest()
            ->get();
    }

    /**
     * Create new application in db
     */
    public function store(StoreApplicationRequest $request)
    {
        $this->authorize('create', Application::class);

        $application = $this->applicationService->createApplication(
            $request->validated(),
            $request->user()
        );

        return response()->json(['application_id' => $application->id], 201);
    }

    /**
     * Select application and the evaluation status
     */
    public function show(Request $request, int $id)
    {
        $application = Application::with(['studentProfile.user', 'team', 'call'])
            ->withCount(['evaluations as completed_evaluations_count' => function ($query) {
                $query->where('status', 'completed');
            }])
            ->findOrFail($id);

        $this->authorize('view', $application);

        $totalEvaluators = CallEvaluator::where('call_id', $application->call_id)->count();
        
        $application->total_evaluators_count = $totalEvaluators;
        $application->pending_evaluators_count = max(0, $totalEvaluators - $application->completed_evaluations_count);

        return response()->json($application);
    }

    /**
     * Update applications data by student (if application is in draft/pending_revision status)
     */
    public function update(Request $request, int $id)
    {
        $application = Application::findOrFail($id);

        $this->authorize('update', $application);

        $data = $request->validate([
            'category' => 'nullable|string|max:255',
            'team_id' => 'nullable|exists:teams,id',
        ]);

        $application->update($data);

        return response()->json(['message' => 'Application updated successfully', 'application' => $application]);
    }

    /**
     * Submitting changes in application with status pending_revision
     */
    public function applyChanges(Request $request, int $id)
    {
        $application = Application::findOrFail($id);

        $this->authorize('applyChanges', $application);

        $this->applicationService->applyChanges($application, $request->user());

        return response()->json([
            'message' => 'Application submitted successfully',
            'status' => 'under_evaluation',
        ]);
    }

    /**
     * Submitting an application from the draft
     */
    public function submitDraft(Request $request, int $id)
    {
        $application = Application::findOrFail($id);

        $this->authorize('submitDraft', $application);

        $this->applicationService->submitDraft($application, $request->user());

        return response()->json([
            'message' => 'Application submitted successfully',
            'status' => 'submitted',
        ]);
    }

    /**
     * Delete application (only draft for student)
     */
    public function destroy(Request $request, int $id)
    {
        $application = Application::findOrFail($id);

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

        $statusValidationRule = $user->isAdmin()
            ? 'required|in:submitted,formally_verified,under_evaluation,pending_revision,approved,rejected,onboarding,active,suspended,closed'
            : 'required|in:onboarding,active,approved,suspended,closed';

        $request->validate([
            'status' => $statusValidationRule,
            'comment' => 'nullable|string|max:1000',
        ]);

        $this->applicationWorkflowService->updateStatus($application, $request->status, $request->comment, $user);

        return response()->json(['message' => "Application status updated successfully to {$request->status}."]);
    }

    /**
     * Selects last revision request (for student to check comment)
     */
    public function getLastRevisionRequest(Application $application)
    {
        $this->authorize('view', $application);

        return response()->json(
            ApplicationRevisionRequest::where('application_id', $application->id)
                ->latest()
                ->first()
        );
    }
}
