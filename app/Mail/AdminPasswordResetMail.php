<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class AdminPasswordResetMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public User $user,
        public string $temporaryPassword
    ) {}

    public function build()
    {
        return $this->subject('Admin Password Reset')
            ->view('emails.adminPasswordReset')
            ->with([
                'user' => $this->user,
                'temporaryPassword' => $this->temporaryPassword,
                'loginUrl' => env('FRONTEND_URL').'/login',
            ]);
    }
}