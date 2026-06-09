<?php

namespace Database\Factories;

use App\Models\Application;
use App\Models\Call;
use App\Models\StudentProfile;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class ApplicationFactory extends Factory
{
    protected $model = Application::class;

    public function definition(): array
    {
        $status = fake()->randomElement([
            'draft', 'submitted', 'formally_verified',
            'under_evaluation', 'pending_revision', 'approved', 'rejected', 'closed',
        ]);

        $isDecisionMade = in_array($status, ['approved', 'rejected', 'closed']);

        return [
            'call_id' => Call::factory(),
            'applicant_type' => fake()->randomElement(['student', 'team']),
            'program_type' => fake()->randomElement(['a', 'b']),
            'student_profile_id' => StudentProfile::factory(),
            'team_id' => null,
            'status' => $status,
            'submitted_at' => $status !== 'draft' ? fake()->dateTimeBetween('-1 month', 'now') : null,
            'decision_at' => $isDecisionMade ? fake()->dateTimeBetween('now', '+2 weeks') : null,
            'decision_by' => $isDecisionMade ? User::factory() : null,
            'internal_notes' => fake()->optional()->sentence(),
        ];
    }

    public function individual(): static
    {
        return $this->state(fn (array $attributes) => [
            'applicant_type' => 'student',
            'team_id' => null,
        ]);
    }

    public function teamApplication(int $teamId): static
    {
        return $this->state(fn (array $attributes) => [
            'applicant_type' => 'team',
            'team_id' => $teamId,
        ]);
    }
}
