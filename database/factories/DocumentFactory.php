<?php

namespace Database\Factories;

use App\Models\Document;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class DocumentFactory extends Factory
{
    protected $model = Document::class;

    public function definition(): array
    {
        $extensions = ['pdf', 'docx'];
        $ext = fake()->randomElement($extensions);
        $fileName = fake()->word() . '.' . $ext;

        $mimeTypes = [
            'pdf' => 'application/pdf',
            'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'
        ];

        return [
            'uploaded_by' => User::factory(), 
            'type' => fake()->randomElement(['cv', 'academic_transcript', 'budget_plan', 'technical_specification']),
            'classification' => 'public',
            'version' => '1.0',
            'file_path' => 'uploads/documents/' . fake()->uuid() . '.' . $ext,
            'file_name' => $fileName,
            'mime_type' => $mimeTypes[$ext],
            'file_size_bytes' => fake()->numberBetween(102400, 5242880),
        ];
    }

    public function withType($type): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => $type,
        ]);
    }
}