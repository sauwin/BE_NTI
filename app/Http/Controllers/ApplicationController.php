<?php

namespace App\Http\Controllers;

use App\Mail\ApplicationSubmittedMail;
use App\Mail\ProjectClosedMail;
use App\Mail\StatusChangedMail;
use App\Models\Application;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;

class ApplicationController extends Controller
{
    public function store(Request $request)
    {
        $data = $request->validate([
            'call_id' => 'required', // ТИМЧАСОВО required|exists:calls,id'
            'applicant_type' => 'required|in:student,team',
            'program_type' => 'required|in:a,b',
            'team_id' => 'nullable|exists:teams,id',
        ]);

        $application = Application::create([
            'call_id' => $data['call_id'],
            'applicant_type' => $data['applicant_type'],
            'program_type' => $data['program_type'],
            'team_id' => $data['team_id'] ?? null,
            'student_profile_id' => null,
            'status' => 'draft',
        ]);

        Mail::to($request->user()->email)->send(
            new ApplicationSubmittedMail($request->user(), $application)
        );

        return response()->json([
            'application_id' => $application->id,
        ], 201);
    }

    public function updateStatus(Request $request, int $id)
    {
        $data = $request->validate([
            'status' => 'required|in:draft,submitted,formally_verified,under_evaluation,pending_revision,approved,rejected,onboarding,active,suspended,closed',
        ]);

        $application = Application::findOrFail($id);
        $oldStatus = $application->status;
        $application->update(['status' => $data['status']]);

        $user = $request->user();

        if ($data['status'] === 'closed') {
            Mail::to($user->email)->send(new ProjectClosedMail($user, $application));
        } else {
            Mail::to($user->email)->send(new StatusChangedMail($user, $application, $oldStatus));
        }

        return response()->json(['status' => $application->status]);
    }
}
