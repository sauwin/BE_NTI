<?php

namespace App\Mail;

use App\Models\Application;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ApplicationRevisionSubmittedMail extends Mailable
{
    use Queueable, SerializesModels;

    public User $user;
    public Application $application;

    /**
     * Create a new message instance.
     */
    public function __construct(User $user, Application $application)
    {
        $this->user = $user;
        $this->application = $application;
    }

    /**
     * Get the message envelope
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'NTI - Akceptácia zmien v prihláške #' . $this->application->id,
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            view: 'emails.application_revision_submitted',
        );
    }
}
