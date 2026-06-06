<?php

namespace Database\Factories;

use App\Models\Call;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class CallFactory extends Factory
{
    protected $model = Call::class;

    public function definition(): array
    {
        return [
            'program' => $this->faker->randomElement(['a', 'b']),
            'name' => $this->faker->sentence(3),
            'status' => 'draft',
            'opens_at' => now(),
            'deadline_at' => now()->addDays(7),
            'min_team_size' => 3,
            'max_team_size' => 5,
            'evaluation_criteria' => [],
            'required_documents' => json_encode([
                ['document_name' => 'Project Proposal', 'is_mandatory' => false, 'max_size_mb' => 10, 'type' => 'project_proposal'],
                ['document_name' => 'Technical Documentation', 'is_mandatory' => false, 'max_size_mb' => 15, 'type' => 'technical_documentation'],
            ]),
            'created_by' => User::factory(),
        ];
    }
}