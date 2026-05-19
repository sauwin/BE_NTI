<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Contracts\Queue\ShouldQueue;

class PasswordResetMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public User $user,
        public string $token
    ) {}

    public function build()
    {
        $resetUrl = env('FRONTEND_URL').'/auth/reset-password?token='.$this->token;

        return $this->subject('Reset Your Password')
            ->view('emails.passwordReset')
            ->with([
                'user' => $this->user,
                'resetUrl' => $resetUrl,
                'expiresIn' => '1 hour',
            ]);
    }
}