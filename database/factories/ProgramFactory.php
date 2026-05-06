<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class ProgramFactory extends Factory
{
    public function definition(): array
    {
        return [
            'code' => $this->faker->unique()->word(),
            'type' => $this->faker->randomElement(['grant', 'live_practice']),
            'is_active' => true,
        ];
    }

    public function programA()
    {
        return $this->state([
            'code' => 'program_a',
            'type' => 'grant',
        ]);
    }

    public function programB()
    {
        return $this->state([
            'code' => 'program_b',
            'type' => 'live_practice',
        ]);
    }
}