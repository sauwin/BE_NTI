<?php

namespace App\Mail;

use App\Models\Milestone;
use App\Models\User;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class MilestoneStatusChangedMail extends Mailable
{
    public function __construct(
        public User $user,
        public Milestone $milestone,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Milestone Status Updated — NTI');
    }

    public function content(): Content
    {
        return new Content(view: 'emails.milestoneStatusChanged');
    }
}
