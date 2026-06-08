<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreCallRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        return [
            'program_type' => 'required|in:a,b',
            'title' => 'nullable|string|max:255',
            'name' => 'nullable|string|max:255',
            'status' => 'sometimes|required|in:draft,open,closed,archived',
            'opens_at' => 'nullable|date',
            'deadline_at' => 'nullable|date',
            'min_team_size' => 'nullable|integer|min:1',
            'max_team_size' => 'nullable|integer',
            'evaluation_criteria' => 'nullable',
            'required_documents' => 'nullable',
            'form_config' => 'nullable',
        ];
    }
}
