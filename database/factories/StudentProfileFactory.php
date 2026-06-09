<?php

namespace Database\Factories;

use App\Models\Document;
use App\Models\StudentProfile;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class StudentProfileFactory extends Factory
{
    protected $model = StudentProfile::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'study_program' => fake()->randomElement([
                'Aplikovaná informatika',
                'Umelá inteligencia',
            ]),
            'year_of_study' => fake()->numberBetween(1, 5),
            'university' => fake()->randomElement([
                'Univerzita Konštantína Filozofa v Nitre',
                'Slovenská Poľnohospodarská Univerzita v Nitre',
            ]),
            'bio' => fake()->paragraph(),
            'github_url' => fake()->url(),
            'academic_declaration_confirmed' => true,
            'cv_document_id' => null,
        ];
    }

    public function withCv(): static
    {
        return $this->state(fn (array $attributes) => [
            'cv_document_id' => Document::factory()->withType('cv'),
        ]);
    }
}
