<?php

namespace Database\Factories;

use App\Models\Team;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class TeamFactory extends Factory
{
    protected $model = Team::class;

    public function definition(): array
    {
        return [
            'name' => fake()->company() . ' Team',
            'leader_id' => User::factory(),
            'description' => fake()->paragraph(),
            'status' => 'forming',
        ];
    }

    public function forming(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'forming',
        ]);
    }

    public function ready(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'ready',
        ]);
    }
}