<?php
namespace App\Mail;

use App\Models\User;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class DeadlineReminderMail extends Mailable
{
    public function __construct(
        public User $user,
        public object $call,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Application Deadline Approaching — NTI');
    }

    public function content(): Content
    {
        return new Content(view: 'emails.deadlineReminder');
    }
}
