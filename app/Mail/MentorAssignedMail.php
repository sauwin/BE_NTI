<?php
namespace App\Mail;

use App\Models\User;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Contracts\Queue\ShouldQueue;

class MentorAssignedMail extends Mailable
{
    public function __construct(
        public User $student,
        public User $mentor,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Mentor Assigned — NTI');
    }

    public function content(): Content
    {
        return new Content(view: 'emails.mentorAssigned');
    }
}