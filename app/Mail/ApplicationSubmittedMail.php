<?php
namespace App\Mail;

use App\Models\Application;
use App\Models\User;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Contracts\Queue\ShouldQueue;

class ApplicationSubmittedMail extends Mailable
{
    public function __construct(
        public User $user,
        public Application $application
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Application Submitted — NTI Program ' . strtoupper($this->application->program_type));
    }

    public function content(): Content
    {
        return new Content(view: 'emails.applicationSubmitted');
    }
}