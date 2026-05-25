<?php

namespace Database\Seeders;

use App\Models\NewsArticle;
use App\Models\NewsArticleTranslation;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            RoleSeeder::class,
            UserSeeder::class,
            ProgramSeeder::class,
            NewsArticleSeeder::class,
            AdminUserSeeder::class,
            FaqSeeder::class,
            CallPeriodSeeder::class,
            TeamAndApplicationSeeder::class,
        ]);

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
