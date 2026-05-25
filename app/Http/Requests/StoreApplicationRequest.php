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
            'submit_type' => 'nullable|in:draft,final',
            'category' => 'nullable|string|max:255',
        ];
    }
}
