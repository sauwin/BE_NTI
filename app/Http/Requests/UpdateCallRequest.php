<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateCallRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        return [
            'title' => 'sometimes|string|max:255',
            'program_type' => 'sometimes|in:a,b',
            'status' => 'sometimes|in:draft,open,closed,archived',
            'opens_at' => 'nullable|date',
            'deadline_at' => 'nullable|date|after_or_equal:opens_at',
            'min_team_size' => 'sometimes|integer|min:1',
            'max_team_size' => 'nullable|integer|min:1',
            'evaluation_criteria' => 'nullable|array',
            'form_config' => 'nullable|string',
            'required_documents' => 'nullable|array',
        ];
    }
}
