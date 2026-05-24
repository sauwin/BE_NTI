<?php

namespace App\Http\Controllers;

use App\Mail\MilestoneStatusChangedMail;
use App\Models\Application;
use App\Models\Document;
use App\Models\Milestone;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;

class MilestoneController extends Controller
{
    public function index(Request $request, int $id)
    {
        $user = $request->user();
        $application = Application::findOrFail($id);

        if (! $user->roles()->whereIn('slug', ['super_admin', 'nti_admin', 'mentor'])->exists()) {
            $ownedByStudent = $application->studentProfile && $application->studentProfile->user_id === $user->id;
            $ownedByTeam = $application->team && $application->team->members()->where('users.id', $user->id)->exists();
            if (! $ownedByStudent && ! $ownedByTeam) {
                return response()->json(['message' => 'Unauthorized'], 403);
            }
        }

        return response()->json(Milestone::where('application_id', $id)->get());
    }

    public function store(Request $request, int $id)
    {
        $user = $request->user();
        if (! $user->roles()->whereIn('slug', ['super_admin', 'nti_admin', 'mentor'])->exists()) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        Application::findOrFail($id);

        $data = $request->validate([
            'title' => 'required|string|max:255',
            'due_date' => 'required|date',
            'description' => 'nullable|string',
        ]);

        $milestone = Milestone::create([
            'application_id' => $id,
            'name' => $data['title'],
            'due_date' => $data['due_date'],
            'description' => $data['description'] ?? null,
            'status' => 'pending',
        ]);

        return response()->json($milestone, 201);
    }

    public function show(Request $request, int $id)
    {
        $milestone = Milestone::with('documents')->findOrFail($id);
        $user = $request->user();

        if (! $user->roles()->whereIn('slug', ['super_admin', 'nti_admin', 'mentor'])->exists()) {
            $application = $milestone->application;
            $ownedByStudent = $application->studentProfile && $application->studentProfile->user_id === $user->id;
            $ownedByTeam = $application->team && $application->team->members()->where('users.id', $user->id)->exists();
            if (! $ownedByStudent && ! $ownedByTeam) {
                return response()->json(['message' => 'Unauthorized'], 403);
            }
        }

        return response()->json($milestone);
    }

    public function update(Request $request, int $id)
    {
        $user = $request->user();
        if (! $user->roles()->whereIn('slug', ['super_admin', 'nti_admin', 'mentor'])->exists()) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $milestone = Milestone::findOrFail($id);

        $data = $request->validate([
            'status' => 'required|in:pending,in_progress,completed,overdue',
        ]);

        $milestone->status = $data['status'];
        if ($data['status'] === 'completed') {
            $milestone->completed_at = now();
        }
        $milestone->save();

        $application = $milestone->application;
        $recipientUser = null;
        if ($application->studentProfile) {
            $recipientUser = $application->studentProfile->user;
        }

        if ($recipientUser) {
            Mail::to($recipientUser->email)->queue(new MilestoneStatusChangedMail($recipientUser, $milestone));
        }

        return response()->json($milestone);
    }

    public function uploadDocument(Request $request, int $id)
    {
        $milestone = Milestone::findOrFail($id);
        $user = $request->user();

        if (! $user->roles()->whereIn('slug', ['super_admin', 'nti_admin', 'mentor'])->exists()) {
            $application = $milestone->application;
            $ownedByStudent = $application->studentProfile && $application->studentProfile->user_id === $user->id;
            $ownedByTeam = $application->team && $application->team->members()->where('users.id', $user->id)->exists();
            if (! $ownedByStudent && ! $ownedByTeam) {
                return response()->json(['message' => 'Unauthorized'], 403);
            }
        }

        $request->validate(['file' => 'required|file|max:20480']);

        $file = $request->file('file');
        $path = $file->store('documents', 'local');

        $document = Document::create([
            'uploaded_by' => $user->id,
            'type' => 'milestone_attachment',
            'classification' => 'internal',
            'version' => 1,
            'file_path' => $path,
            'file_name' => $file->getClientOriginalName(),
            'mime_type' => $file->getMimeType(),
            'file_size_bytes' => $file->getSize(),
        ]);

        $milestone->documents()->attach($document->id);

        return response()->json(['document_id' => $document->id, 'file_name' => $document->file_name], 201);
    }
}
