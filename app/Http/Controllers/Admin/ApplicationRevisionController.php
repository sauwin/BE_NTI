<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Application;
use App\Models\ApplicationRevisionRequest;
use App\Models\StudentProfile;
use App\Services\AdminApplicationService;
use Illuminate\Http\Request;

class ApplicationRevisionController extends Controller
{
    protected $adminService;

    public function __construct(AdminApplicationService $adminService)
    {
        $this->adminService = $adminService;
    }

    public function requestRevision(Request $request, int $id)
    {
        $request->validate([
            'message' => 'required|string|min:5|max:2000'
        ]);

        $application = Application::findOrFail($id);
        
        $this->adminService->createRevisionRequest($application, $request->message, $request->user());

        return response()->json(['message' => 'Revision request created successfully and student notified.']);
    }

    public function getRevisionHistory(Request $request, int $id)
    {
        $application = Application::findOrFail($id);

        if ($request->user()->roles->contains('slug', 'student')) {
            $profile = StudentProfile::where('user_id', $request->user()->id)->first();
            if (!$profile || $application->student_profile_id !== $profile->id) {
                return response()->json(['message' => 'Unauthorized'], 403);
            }
        }

        $requests = ApplicationRevisionRequest::where('application_id', $id)
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json($requests);
    }
}