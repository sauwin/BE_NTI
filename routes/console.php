<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

use App\Jobs\StartScheduledEvaluations;

Schedule::command('nti:deadlineReminders')->dailyAt('09:00');
Schedule::command('calls:closeExpired')->hourly();
Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::job(new StartScheduledEvaluations)->hourly();
