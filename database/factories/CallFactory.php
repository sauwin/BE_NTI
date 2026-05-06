<?php

namespace Database\Factories;

use App\Models\Call;
use App\Models\Program;
use Illuminate\Database\Eloquent\Factories\Factory;

class CallFactory extends Factory
{
    protected $model = Call::class;

    public function definition(): array
    {
        return [
            'program_id' => Program::factory(),
            'status' => 'open',
            'opens_at' => now(),
            'deadline_at' => now()->addDays(7),
            'min_team_size' => 3,
            'max_team_size' => 5,
            'evaluation_criteria' => [],
            'required_documents' => [],
            'created_by' => 1,
        ];
    }
}