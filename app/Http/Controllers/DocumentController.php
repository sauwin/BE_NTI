<?php

namespace App\Http\Controllers;

use App\Models\Document;
use App\Models\ApplicationDocument;
use Illuminate\Http\Request;

class DocumentController extends Controller
{
    public function upload(Request $request)
    {
        $request->validate([
            'file'           => 'required|file|max:20480|mimes:pdf,doc,docx,ppt,pptx',
            'type'           => 'required|in:cv,executive_summary,technical_architecture,roadmap,budget,risk_analysis,monetization,motivation_letter,technical_proposal,final_presentation,other',
            'classification' => 'in:public,internal,confidential',
            'application_id' => 'required|exists:applications,id',
        ]);

        $file = $request->file('file');
        $path = $file->store('documents', 'local');

        $document = Document::create([
            'uploaded_by'     => $request->user()->id,
            'type'            => $request->type,
            'classification'  => $request->input('classification', 'internal'),
            'version'         => 1,
            'file_path'       => $path,
            'file_name'       => $file->getClientOriginalName(),
            'mime_type'       => $file->getMimeType(),
            'file_size_bytes' => $file->getSize(),
        ]);

        ApplicationDocument::create([
            'application_id' => $request->application_id,
            'document_id'    => $document->id,
        ]);

        return response()->json([
            'document_id' => $document->id,
            'file_name'   => $document->file_name,
        ], 201);
    }
}