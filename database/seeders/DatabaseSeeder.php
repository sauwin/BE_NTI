<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

use App\Models\User;
use App\Models\NewsArticle;
use App\Models\NewsArticleTranslation;
use App\Models\Program;
use App\Models\Call;

class DatabaseSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
    $user = User::factory()->create();

    User::factory(4)->create();

    NewsArticle::factory(30)
        ->has(
            NewsArticleTranslation::factory()->state(['language' => 'en']),
            'translation'
        )
        ->has(
            NewsArticleTranslation::factory()->state(['language' => 'sk']),
            'translation'
        )
        ->create();

        $this->call([
            ProgramSeeder::class,
        ]);
    }
}
