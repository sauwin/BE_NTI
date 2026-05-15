<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\NewsArticle;
use App\Models\NewsArticleTranslation;

class NewsArticleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        NewsArticle::factory(30)
        ->has(
            NewsArticleTranslation::factory()->state(['language' => 'en']),
            'translations'
        )
        ->has(
            NewsArticleTranslation::factory()->state(['language' => 'sk']),
            'translations'
        )
        ->create();
    }
}
