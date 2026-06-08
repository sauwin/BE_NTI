<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreApplicationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'applicant_type' => 'required|in:student,team',
            'program_type' => 'required|in:a,b',
            'team_id' => 'nullable|required_if:applicant_type,team|exists:teams,id',
            'call_id' => 'nullable|integer|exists:calls,id',
            'submit_type' => 'nullable|in:draft,final',
            'category' => 'nullable|string|max:255',
            'project_title' => 'nullable|required_if:program_type,b|string|max:255',
            'proposed_solution' => 'nullable|required_if:program_type,b|string',
            'academic_declaration' => 'nullable|boolean',
            'documents' => 'nullable|array',
            'documents.*.file' => 'required_with:documents|file|mimes:pdf,doc,docx,ppt,pptx,xls,xlsx,png,jpeg,zip|max:20480',
            'documents.*.type' => 'required_with:documents|string|max:100',
            'documents.*.classification' => 'required_with:documents|in:public,internal,confidential',
        ];
    }
}
