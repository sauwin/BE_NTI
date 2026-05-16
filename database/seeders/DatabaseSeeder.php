<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

use Database\Seeders\ArticleSeeder;
use Database\Seeders\ProgramSeeder;
use Database\Seeders\RoleSeeder;
use Database\Seeders\UserSeeder;
use Database\Seeders\AdminUserSeeder;
use Database\Seeders\FaqSeeder;

use App\Models\User;
use App\Models\NewsArticle;
use App\Models\NewsArticleTranslation;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            UserSeeder::class,
            ProgramSeeder::class,
            RoleSeeder::class,
            NewsArticleSeeder::class,
            AdminUserSeeder::class,
            FaqSeeder::class,
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