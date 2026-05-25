<?php

namespace App\Providers;

use App\Models\FaqItem;
use App\Models\NewsArticle;
use App\Policies\FaqItemPolicy;
use App\Policies\NewsArticlePolicy;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
use App\Models\Application;
use App\Policies\ApplicationPolicy;

class AuthServiceProvider extends ServiceProvider
{
    protected $policies = [
        NewsArticle::class => NewsArticlePolicy::class,
        FaqItem::class => FaqItemPolicy::class,
        Application::class => ApplicationPolicy::class,
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
