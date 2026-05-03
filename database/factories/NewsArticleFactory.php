<?php

namespace Database\Factories;

use App\Models\NewsArticle;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<NewsArticle>
 */
class NewsArticleFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'slug' => $this->faker->slug(),
            'author_id' => User::all()->random()->id,
            'is_published' => $this->faker->boolean(80),
            'published_at' => now(),
        ];
    }
}
