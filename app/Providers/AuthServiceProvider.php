<?php

namespace App\Providers;

use App\Models\Application;
use App\Models\Document;
use App\Models\Evaluation;
use App\Models\FaqItem;
use App\Models\Mentorship;
use App\Models\Milestone;
use App\Models\NewsArticle;
use App\Models\Organization;
use App\Models\Task;
use App\Models\Team;
use App\Policies\ApplicationPolicy;
use App\Policies\DocumentPolicy;
use App\Policies\EvaluationPolicy;
use App\Policies\FaqItemPolicy;
use App\Policies\MentorshipPolicy;
use App\Policies\MilestonePolicy;
use App\Policies\NewsArticlePolicy;
use App\Policies\OrganizationPolicy;
use App\Policies\TaskPolicy;
use App\Policies\TeamPolicy;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;

class AuthServiceProvider extends ServiceProvider
{
    protected $policies = [
        NewsArticle::class => NewsArticlePolicy::class,
        FaqItem::class => FaqItemPolicy::class,
        Application::class => ApplicationPolicy::class,
        Document::class => DocumentPolicy::class,
        Milestone::class => MilestonePolicy::class,
        Evaluation::class => EvaluationPolicy::class,
        Task::class => TaskPolicy::class,
        Mentorship::class => MentorshipPolicy::class,
        Team::class => TeamPolicy::class,
        Organization::class => OrganizationPolicy::class,
    ];

    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        $this->registerPolicies();
    }
}
