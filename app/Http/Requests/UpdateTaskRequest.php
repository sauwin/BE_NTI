<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateTaskRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        return [
            'title' => 'sometimes|required|string|max:255',
            'brief' => 'nullable|string|max:2000',
            'budget' => 'nullable|numeric|min:0',
            'status' => 'sometimes|required|in:draft,published,in_matching,assigned,in_progress,closed',
            'short_description' => 'nullable|string|max:500',
            'project_goal' => 'nullable|string',
            'expected_outcome' => 'nullable|string',
            'detailed_technical_description' => 'nullable|string',
            'required_technologies' => 'nullable|array',
            'architecture_requirements' => 'nullable|string',
            'integrations_apis' => 'nullable|string',
            'platforms' => 'nullable|string',
            'required_skills' => 'nullable|array',
            'preferred_team_size' => 'nullable|integer',
            'required_experience' => 'nullable|string',
            'expected_duration' => 'nullable|string',
            'milestones' => 'nullable|string',
            'deadline' => 'nullable|date',
            'product_owner_user_id' => 'nullable|exists:users,id',
            'min_team_size' => 'nullable|number',
            'max_team_size' => 'nullable|number',
            'opens_at' => 'nullable|date',
            'deadline_at' => 'nullable|date',
        ];
    }
}