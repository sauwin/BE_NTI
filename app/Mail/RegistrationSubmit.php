<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\URL;
use App\Models\User;
use Illuminate\Contracts\Queue\ShouldQueue;

class RegistrationSubmit extends Mailable
{
    use Queueable, SerializesModels;

    public string $verifyUrl;

    /**
     * Create a new message instance.
     */
    public function __construct(public User $user)
    {
        // Signed URL valid for 60 minutes, points to BE route
        $this->verifyUrl = URL::temporarySignedRoute(
            'verification.verify',
            now()->addMinutes(60),
            ['id' => $user->id, 'hash' => sha1($user->email)]
        );
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Continue registration',
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            view: 'emails.continueRegistration',
        );
    }
}
