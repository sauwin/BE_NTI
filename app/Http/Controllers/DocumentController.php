<?php

namespace App\Http\Controllers;

use App\Http\Requests\UploadDocumentRequest;
use App\Models\Application;
use App\Models\ApplicationDocument;
use App\Models\Document;
use App\Models\Task;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class DocumentController extends Controller
{
    public function upload(UploadDocumentRequest $request)
    {
        if ($request->has('application_id')) {
            $application = Application::findOrFail($request->application_id);
            $this->authorize('uploadDocument', $application);
        }

        if ($request->has('task_id')) {
            $task = Task::findOrFail($request->task_id);
            $this->authorize('update', $task);
        }

        $userId = $request->user()->id ?? 1;
        $file = $request->file('file');
        $type = $request->type;

        return DB::transaction(function () use ($request, $file, $type, $userId) {

            if ($request->has('application_id')) {
                $this->deleteExistingApplicationDocument($request->application_id, $type);
            }

            if ($request->has('task_id')) {
                $this->deleteExistingTaskDocument($request->task_id, $type);
            }

            $path = $file->store('documents', 'local');

            $document = Document::create([
                'uploaded_by' => $userId,
                'type' => $type,
                'classification' => $request->input('classification', 'internal'),
                'version' => 1,
                'file_path' => $path,
                'file_name' => $file->getClientOriginalName(),
                'mime_type' => $file->getClientMimeType(),
                'file_size_bytes' => $file->getSize(),
            ]);

            if ($request->has('application_id')) {
                ApplicationDocument::create([
                    'application_id' => $request->application_id,
                    'document_id' => $document->id,
                ]);
            }

            if ($request->has('task_id')) {
                $task = Task::findOrFail($request->task_id);
                $task->documents()->attach($document->id);
            }

            return response()->json($document, 201);
        });
    }

    public function index(Request $request)
    {
        $user = $request->user();

        if (! $user || ! $user->roles()->whereIn('slug', ['super_admin', 'nti_admin', 'mentor'])->exists()) {
            return response()->json(['message' => 'Unauthorized. Admin or mentor required.'], 403);
        }

        $documents = Document::query()
            ->select([
                'documents.id',
                'documents.file_name',
                'documents.mime_type',
                'documents.file_size_bytes',
                'documents.created_at',
                'documents.uploaded_by',
            ])
            ->leftJoin('application_documents', 'application_documents.document_id', '=', 'documents.id')
            ->leftJoin('applications', 'applications.id', '=', 'application_documents.application_id')
            ->addSelect([
                'applications.id as application_id',
                DB::raw("CONCAT('Aplikácia #', applications.id) as application_name"),
            ])
            ->when($request->query('search'), function ($query, $search) {
                $query->where('documents.file_name', 'like', '%'.trim($search).'%');
            })
            ->when($request->query('date'), function ($query, $date) {
                $query->whereDate('documents.created_at', $date);
            })
            ->orderBy('documents.created_at', 'desc')
            ->paginate(15);

        return response()->json($documents);
    }

    public function download(Request $request, int $id)
    {
        $document = Document::findOrFail($id);

        $isTaskDocument = DB::table('task_documents')->where('document_id', $document->id)->exists();
        if (! $isTaskDocument) {
            $this->authorize('view', $document);
        }

        if (! Storage::disk('local')->exists($document->file_path)) {
            return response()->json(['message' => 'File not found'], 404);
        }

        return Storage::disk('local')->download($document->file_path, $document->file_name);
    }

    public function preview(Request $request, int $id)
    {
        $document = Document::findOrFail($id);
        $this->authorize('view', $document);

        if (! str_starts_with(strtolower($document->mime_type), 'application/pdf')) {
            return response()->json(['message' => 'Preview is only available for PDF files'], 415);
        }

        if (! Storage::disk('local')->exists($document->file_path)) {
            return response()->json(['message' => 'File not found'], 404);
        }

        return Storage::disk('local')->response(
            $document->file_path,
            $document->file_name,
            [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'inline; filename="'.$document->file_name.'"',
            ]
        );
    }

    public function deleteExistingApplicationDocument(int $applicationId, string $type): void
    {
        $existing = ApplicationDocument::join('documents', 'documents.id', '=', 'application_documents.document_id')
            ->where('application_documents.application_id', $applicationId)
            ->where('documents.type', $type)
            ->select('application_documents.document_id', 'documents.file_path')
            ->first();

        if ($existing) {
            Storage::disk('local')->delete($existing->file_path);
            ApplicationDocument::where('application_id', $applicationId)
                ->where('document_id', $existing->document_id)
                ->delete();
            Document::find($existing->document_id)?->delete();
        }
    }

    public function deleteExistingTaskDocument(int $taskId, string $type): void
    {
        $task = Task::findOrFail($taskId);

        $existing = $task->documents()->where('type', $type)->first();

        if ($existing) {
            Storage::disk('local')->delete($existing->file_path);
            $task->documents()->detach($existing->id);
            $existing->delete();
        }
    }
}
