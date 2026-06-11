<?php

namespace App\Http\Controllers;

use App\Mail\MilestoneStatusChangedMail;
use App\Models\Application;
use App\Models\Document;
use App\Models\Milestone;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;

/**
 * @tags Project Management
 * Endpoints for managing project lifecycle milestones, tracking status transitions, notifying stakeholders of progress changes, and attaching evidentiary documentation to specific milestones.
 */
class MilestoneController extends Controller
{
    /**
     * Select all milestone owned by application
     */
    public function index(Request $request, int $id)
    {
        $application = Application::findOrFail($id);
        $this->authorize('viewAny', [Milestone::class, $application]);
        return response()->json(Milestone::with('documents')->where('application_id', $id)->get());
    }

    /**
     * Create new milestone for application
     */
    public function store(Request $request, int $id)
    {
        $application = Application::findOrFail($id);

        $this->authorize('create', [Milestone::class, $application]);

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

        $student = $milestone->application->studentProfile->user;
        NotificationController::log($student->id, $student->email, 'milestone_added', 'Milestone added for your application');

        return response()->json($milestone, 201);
    }

    /**
     * Show one milestone for user
     */
    public function show(Request $request, int $id)
    {
        $milestone = Milestone::with('documents')->findOrFail($id);

        $this->authorize('view', $milestone);

        return response()->json($milestone);
    }

    /**
     * Update milestone
     */
    public function update(Request $request, int $id)
    {
        $milestone = Milestone::findOrFail($id);

        $this->authorize('update', $milestone);

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

    /**
     * Upload documents for milestone
     */
    public function uploadDocument(Request $request, int $id)
    {
        $milestone = Milestone::findOrFail($id);

        $this->authorize('uploadDocument', $milestone);

        $request->validate(['file' => 'required|file|max:20480']);

        $file = $request->file('file');
        $path = $file->store('documents', 'local');

        $document = Document::create([
            'uploaded_by' => $request->user()->id,
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

    /**
     * Download document from milestones
     */
    public function downloadDocument(Request $request, int $milestoneId, int $documentId)
    {
        $milestone = Milestone::findOrFail($milestoneId);
        $this->authorize('view', $milestone);

        $document = $milestone->documents()->findOrFail($documentId);

        if (! \Storage::disk('local')->exists($document->file_path)) {
            return response()->json(['message' => 'File not found.'], 404);
        }

        return \Storage::disk('local')->download($document->file_path, $document->file_name);
    }
}
