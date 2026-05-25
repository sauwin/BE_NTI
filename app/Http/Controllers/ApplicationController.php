<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreApplicationRequest;
use App\Services\ApplicationService;
use App\Services\AdminApplicationService;
use App\Models\Application;
use App\Models\StudentProfile;
use Illuminate\Http\Request;

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
        
        if (!$profile) {
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
        $application = Application::with(['studentProfile.user', 'team', 'call'])->findOrFail($id);

        $this->authorizeApplicationAccess($request, $application);

        return response()->json($application);
    }

    public function update(Request $request, int $id)
    {
        $application = Application::findOrFail($id);

        if (!in_array($application->status, ['draft', 'pending_revision'])) {
            return response()->json(['message' => 'Je možné upravovať iba koncepty.'], 422);
        }

        $profile = StudentProfile::where('user_id', $request->user()->id)->first();
        if (!$profile || $application->student_profile_id !== $profile->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $data = $request->validate([
            'category' => 'nullable|string|max:255',
            'team_id' => 'nullable|exists:teams,id',
        ]);

        $application->update($data);

        return response()->json(['message' => 'Application updated successfully', 'application' => $application]);
    }

    public function submitDraft(Request $request, int $id)
    {
        $application = Application::findOrFail($id);
        
        $this->applicationService->submitDraft($application, $request->user());

        return response()->json([
            'message' => 'Application submitted successfully',
            'status' => 'submitted'
        ]);
    }

    public function destroy(Request $request, int $id)
    {
        $application = Application::findOrFail($id);

        if ($application->status !== 'draft') {
            return response()->json(['message' => 'Je možné mazať iba koncepty prihlášok.'], 422);
        }

        $profile = StudentProfile::where('user_id', $request->user()->id)->first();
        if (!$profile || $application->student_profile_id !== $profile->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $application->delete();

        return response()->json(['message' => 'Draft deleted successfully']);
    }

    public function documents(Request $request, int $id)
    {
        $application = Application::findOrFail($id);

        $this->authorizeApplicationAccess($request, $application);

        $documents = \App\Models\Document::join('application_documents', 'documents.id', '=', 'application_documents.document_id')
            ->where('application_documents.application_id', $id)
            ->select('documents.*')
            ->get();

        return response()->json($documents);
    }

    protected function authorizeApplicationAccess(Request $request, Application $application): void
    {
        $user = $request->user();

        $isAdminOrMentor = $user->roles->count() > 0 && $user->roles->contains(function ($role) {
            return in_array($role->slug, ['super_admin', 'nti_admin', 'mentor']);
        });

        if ($isAdminOrMentor) {
            return; 
        }

        $profile = StudentProfile::where('user_id', $user->id)->first();
        if (!$profile || $application->student_profile_id !== $profile->id) {
            abort(403, 'Unauthorized');
        }
    }

    public function updateStatus(Request $request, int $id)
    {
        $user = $request->user();
        
        $isAdmin = $user->tokenCan('admin') || $user->role === 'admin' || 
                ($user->roles && $user->roles->contains('slug', 'super_admin'));
                
        $isMentor = $user->roles && $user->roles->contains('slug', 'mentor');

        if (!$isAdmin && !$isMentor) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $allowedMentorStatuses = ['onboarding', 'active', 'approved'];

        $statusValidationRule = $isAdmin 
            ? 'required|in:submitted,formally_verified,under_evaluation,revision_requested,approved,rejected,onboarding,active,suspended,closed'
            : 'required|in:' . implode(',', $allowedMentorStatuses);

        $request->validate([
            'status' => $statusValidationRule,
            'comment' => 'nullable|string|max:1000'
        ]);

        $application = Application::findOrFail($id);

        if ($isMentor && !$isAdmin) {
            $hasMentorship = \App\Models\Mentorship::where('application_id', $application->id)
                ->where('mentor_id', $user->id)
                ->exists();

            if (!$hasMentorship) {
                return response()->json(['message' => 'Unauthorized. You are not assigned to this application.'], 403);
            }
        }
        
        $this->adminService->updateStatus($application, $request->status, $request->comment, $user);

        return response()->json(['message' => "Application status updated successfully to {$request->status}."]);
    }
}