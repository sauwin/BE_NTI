<?php

namespace Database\Factories;

use App\Models\Program;
use Illuminate\Database\Eloquent\Factories\Factory;

class ProgramFactory extends Factory
{
    protected $model = Program::class;

    public function definition(): array
    {
        return [
            'code' => $this->faker->unique()->word(),
            'type' => $this->faker->randomElement([
                Program::TYPE_GRANT,
                Program::TYPE_LIVE,
            ]),
            'is_active' => true,
            'config' => [],
        ];
    }

    public function programA()
    {
        return $this->state([
            'code' => 'program_a',
            'type' => Program::TYPE_GRANT,
        ]);
    }

    public function programB()
    {
        return $this->state([
            'code' => 'program_b',
            'type' => Program::TYPE_LIVE,
        ]);
    }
}