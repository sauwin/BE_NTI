<?php
namespace App\Jobs;

use App\Http\Controllers\NotificationController;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;

class SendBulkNotification implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public int $userId,
        public string $email,
        public string $subject,
        public string $message,
        public string $recipientGroup,
    ) {}

    public function handle(): void
    {
        Mail::html("<p>{$this->message}</p>", function ($m) {
            $m->to($this->email)->subject($this->subject);
        });

        NotificationController::log(
            $this->userId,
            $this->email,
            'bulk_notification',
            $this->message,
            ['recipient_group' => $this->recipientGroup, 'subject' => $this->subject]
        );
    }
}