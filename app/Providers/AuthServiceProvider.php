<?php

namespace App\Providers;

use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
use App\Models\NewsArticle;
use App\Policies\NewsArticlePolicy;

class AuthServiceProvider extends ServiceProvider
{
    protected $policies = [
        NewsArticle::class => NewsArticlePolicy::class,
    ];

    /**
     * Register services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        $this->registerPolicies();
    }
}
