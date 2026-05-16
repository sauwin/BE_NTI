<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

use Database\Seeders\ArticleSeeder;
use Database\Seeders\ProgramSeeder;
use Database\Seeders\RoleSeeder;
use Database\Seeders\UserSeeder;
use Database\Seeders\FaqSeeder;

use App\Models\User;
use App\Models\NewsArticle;
use App\Models\NewsArticleTranslation;

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
            UserSeeder::class,
            ProgramSeeder::class,
            RoleSeeder::class,
            NewsArticleSeeder::class,
            AdminUserSeeder::class,
            FaqSeeder::class,
        ]);
    }
}
