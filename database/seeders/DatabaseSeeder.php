<?php

namespace Database\Seeders;

use App\Models\NewsArticle;
use App\Models\NewsArticleTranslation;
use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        User::factory(5)->create();

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

        $this->call([
            ProgramSeeder::class,
            RoleSeeder::class,
        ]);
    }
}
