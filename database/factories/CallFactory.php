<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use App\Models\Program;

class CallFactory extends Factory
{
    public function definition(): array
    {
        return [
            'program_id' => Program::factory(),
            'status' => 'open',
            'created_by' => 1,
            'min_team_size' => 3,
        ];
    }

    public function forProgram($program)
    {
        return $this->state([
            'program_id' => $program->id,
        ]);
    }
}