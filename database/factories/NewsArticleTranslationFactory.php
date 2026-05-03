<?php

namespace Database\Factories;

use App\Models\NewsArticleTranslation;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<NewsArticleTranslation>
 */
class NewsArticleTranslationFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $title = $this->faker->sentence();
        
        return [
            'title' => $title,
            'language' => 'en',
            'excerpt' => $this->faker->text(),
            'content' => $this->faker->paragraphs(3, true),
        ];
    }
}
