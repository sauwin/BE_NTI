<?php

namespace App\Notifications;

use App\Models\Call;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class EvaluationAssigned extends Notification implements ShouldQueue
{
    use Queueable;

    protected $call;

    public function __construct(Call $call)
    {
        $this->call = $call;
    }

    public function via($notifiable)
    {
        return ['mail', 'database'];
    }

    public function toMail($notifiable)
    {
        return (new MailMessage)
            ->subject('You have been assigned as an evaluator')
            ->line('You have been assigned as an evaluator for the call: '.$this->call->name)
            ->line('Deadline: '.$this->call->deadline->format('Y-m-d'))
            ->action('View Applications', url('/dashboard/evaluations?call='.$this->call->id))
            ->line('Thank you for your participation!');
    }

    public function toDatabase($notifiable)
    {
        return [
            'call_id' => $this->call->id,
            'call_name' => $this->call->name,
            'type' => 'evaluation_assigned',
            'message' => 'You have been assigned as an evaluator for: '.$this->call->name,
        ];
    }
}
