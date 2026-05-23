<?php

namespace App\Http\Controllers;

use App\Models\ApplicationDocument;
use App\Models\Document;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class DocumentController extends Controller
{
    public function upload(Request $request)
    {
        $request->validate([
            'file' => 'required|file|max:20480|mimes:pdf,doc,docx,ppt,pptx',
            'type' => 'required|string|max:100',
            'classification' => 'in:public,internal,confidential',
            'application_id' => 'required|exists:applications,id',
        ]);

        $existing = ApplicationDocument::join('documents', 'documents.id', '=', 'application_documents.document_id')
            ->where('application_documents.application_id', $request->application_id)
            ->where('documents.type', $request->type)
            ->select('application_documents.document_id', 'documents.file_path')
            ->first();

        if ($existing) {
            \Storage::disk('local')->delete($existing->file_path);
            ApplicationDocument::where('application_id', $request->application_id)
                ->where('document_id', $existing->document_id)
                ->delete();
            Document::find($existing->document_id)?->delete();
        }

        $file = $request->file('file');
        $path = $file->store('documents', 'local');

        $document = Document::create([
            'uploaded_by' => $request->user()->id,
            'type' => $request->type,
            'classification' => $request->input('classification', 'internal'),
            'version' => 1,
            'file_path' => $path,
            'file_name' => $file->getClientOriginalName(),
            'mime_type' => $file->getMimeType(),
            'file_size_bytes' => $file->getSize(),
        ]);

        ApplicationDocument::create([
            'application_id' => $request->application_id,
            'document_id' => $document->id,
        ]);

        return response()->json([
            'document_id' => $document->id,
            'file_name' => $document->file_name,
        ], 201);
    }

    public function index(Request $request)
    {
        $user = $request->user();

        if (! $user || ! $user->roles()->whereIn('slug', ['super_admin', 'nti_admin', 'mentor'])->exists()) {
            return response()->json(['message' => 'Unauthorized. Admin or mentor required.'], 403);
        }

        $documents = Document::query()
            ->select(['id', 'file_name', 'mime_type', 'file_size_bytes', 'created_at', 'uploaded_by'])
            ->when($request->query('search'), function ($query, $search) {
                $query->where('file_name', 'like', '%' . trim($search) . '%');
            })
            ->when($request->query('date'), function ($query, $date) {
                $query->whereDate('created_at', $date);
            })
            ->orderBy('created_at', 'desc')
            ->paginate(15);

        return response()->json($documents);
    }

    public function download(Request $request, int $id)
    {
        $document = Document::findOrFail($id);
        $this->authorizeDocumentAccess($request, $document);

        if (! Storage::disk('local')->exists($document->file_path)) {
            return response()->json(['message' => 'File not found'], 404);
        }

        return Storage::disk('local')->download($document->file_path, $document->file_name);
    }

    public function preview(Request $request, int $id)
    {
        $document = Document::findOrFail($id);
        $this->authorizeDocumentAccess($request, $document);

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
                'Content-Disposition' => 'inline; filename="' . $document->file_name . '"',
            ]
        );
    }

    private function authorizeDocumentAccess(Request $request, Document $document): void
    {
        $user = $request->user();

        if ($user->roles()->whereIn('slug', ['super_admin', 'nti_admin', 'mentor'])->exists()) {
            return;
        }

        if ($document->uploaded_by === $user->id) {
            return;
        }

        abort(403, 'Forbidden');
    }
}
