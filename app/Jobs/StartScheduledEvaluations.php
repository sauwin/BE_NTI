<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

use App\Models\Call;

class StartScheduledEvaluations implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new job instance.
     */
    public function __construct()
    {
        //
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        Call::query()
            ->whereNotNull('evaluation_scheduled_at')
            ->where('evaluation_scheduled_at', '<=', now())
            ->where('status', 'open')
            ->get()
            ->each(function (Call $call) {
                $call->startEvaluation();
            });
    }
}
