<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreApplicationRequest;
use App\Services\ApplicationService;
use App\Models\Application;
use App\Models\StudentProfile;
use Illuminate\Http\Request;

class ApplicationController extends Controller
{
    protected $applicationService;

    public function __construct(ApplicationService $applicationService)
    {
        $this->applicationService = $applicationService;
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

        $profile = StudentProfile::where('user_id', $request->user()->id)->first();
        if (!$profile || $application->student_profile_id !== $profile->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

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

        $profile = StudentProfile::where('user_id', $request->user()->id)->first();
        if (!$profile || $application->student_profile_id !== $profile->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $documents = \App\Models\Document::whereHas('applications', function($query) use ($id) {
            $query->where('application_id', $id);
        })->get();

        return response()->json($documents);
    }
}