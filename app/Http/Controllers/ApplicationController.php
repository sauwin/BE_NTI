<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreApplicationRequest;
use App\Models\Application;
use App\Models\StudentProfile;
use App\Services\AdminApplicationService;
use App\Services\ApplicationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ApplicationController extends Controller
{
    protected $applicationService;

    protected $adminService;

    public function __construct(ApplicationService $applicationService, AdminApplicationService $adminService)
    {
        $this->applicationService = $applicationService;
        $this->adminService = $adminService;
    }

    public function index(Request $request)
    {
        $profile = StudentProfile::where('user_id', $request->user()->id)->first();

        if (! $profile) {
            return response()->json([], 200);
        }

        $applications = Application::with(['team', 'call'])
            ->where('student_profile_id', $profile->id)
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json($applications);
    }

    public function store(StoreApplicationRequest $request)
    {
        $application = $this->applicationService->createApplication(
            $request->validated(),
            $request->user()
        );

        return response()->json(['application_id' => $application->id], 201);
    }

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

    public function update(Request $request, int $id)
    {
        $application = Application::findOrFail($id);

        if (! in_array($application->status, ['draft', 'pending_revision'])) {
            return response()->json(['message' => 'Je možné upravovať iba koncepty.'], 422);
        }

        $this->authorize('update', $application);

        $data = $request->validate([
            'category' => 'nullable|string|max:255',
            'team_id' => 'nullable|exists:teams,id',
        ]);

        $application->update($data);

        return response()->json(['message' => 'Application updated successfully', 'application' => $application]);
    }

    public function applyChanges(Request $request, int $id)
    {
        $application = Application::findOrFail($id);

        $this->applicationService->applyChanges($application, $request->user());

        return response()->json([
            'message' => 'Application submitted successfully',
            'status' => 'submitted',
        ]);
    }

    public function submitDraft(Request $request, int $id)
    {
        $application = Application::findOrFail($id);

        $this->applicationService->submitDraft($application, $request->user());

        return response()->json([
            'message' => 'Application submitted successfully',
            'status' => 'submitted',
        ]);
    }

    public function destroy(Request $request, int $id)
    {
        $application = Application::findOrFail($id);

        if ($application->status !== 'draft') {
            return response()->json(['message' => 'Je možné mazať iba koncepty prihlášok.'], 422);
        }

        $this->authorize('delete', $application);

        $application->delete();

        return response()->json(['message' => 'Draft deleted successfully']);
    }

    public function documents(Request $request, int $id)
    {
        $application = Application::findOrFail($id);

        $this->authorize('view', $application);

        $documents = \App\Models\Document::join('application_documents', 'documents.id', '=', 'application_documents.document_id')
            ->where('application_documents.application_id', $id)
            ->select('documents.*')
            ->get();

        return response()->json($documents);
    }

    public function updateStatus(Request $request, int $id)
    {
        $user = $request->user();
        $application = Application::with('call.task')->findOrFail($id);

        $this->authorize('updateStatus', $application);

        $isAdmin = $user->hasRole(['nti_admin', 'super_admin']);
        
        $isOrganizationOwner = DB::table('users')
            ->where('id', $user->id)
            ->where('role_in_org', 'owner')
            ->where('organization_id', function($q) use ($application) {
                $q->select('organization_id')
                  ->from('tasks')
                  ->where('call_id', $application->call_id)
                  ->limit(1);
            })->exists();

        if ($isAdmin) {
            $allowedStatuses = [
                'submitted', 'formally_verified', 'under_evaluation', 'pending_revision', 
                'approved', 'rejected', 'onboarding', 'active', 'suspended', 'closed'
            ];
        } elseif ($isOrganizationOwner) {
            $allowedStatuses = ['formally_verified', 'under_evaluation', 'rejected', 'pending_revision'];
        } else {
            $allowedStatuses = ['onboarding', 'active', 'approved', 'suspended', 'closed'];
        }

        $request->validate([
            'status' => 'required|in:' . implode(',', $allowedStatuses),
            'comment' => 'nullable|string|max:1000',
        ]);

        // if ($request->status === 'formally_verified' && $application->status !== 'submitted') {
        //     return response()->json(['message' => 'Only submitted applications can be formally verified.'], 422);
        // }

        $this->adminService->updateStatus($application, $request->status, $request->comment, $user);

        return response()->json(['message' => "Application status updated successfully to {$request->status}."]);
    }

    public function organizationApplications(Request $request)
    {
        $user = $request->user();

        $organizationId = DB::table('users')
            ->where('id', $user->id)
            ->where('role_in_org', 'owner')
            ->value('organization_id');

        if (!$organizationId) {
            return response()->json(['message' => 'Forbidden. You are not an owner of any organization.'], 403);
        }

        $applications = Application::with(['team', 'call.task', 'studentProfile.user'])
            ->whereHas('call.task', function ($query) use ($organizationId) {
                $query->where('organization_id', $organizationId);
            })
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json($applications);
    }
}
