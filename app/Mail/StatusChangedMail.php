<?php
namespace App\Mail;

use App\Models\Application;
use App\Models\User;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class StatusChangedMail extends Mailable
{
    public function __construct(
        public User $user,
        public Application $application,
        public string $oldStatus,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Application Status Update — NTI');
    }

    public function content(): Content
    {
        return new Content(view: 'emails.statusChanged');
    }
}