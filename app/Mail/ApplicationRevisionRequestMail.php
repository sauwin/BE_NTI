<?php

namespace App\Mail;

use App\Models\Application;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class ApplicationRevisionRequestMail extends Mailable
{
    use Queueable, SerializesModels;

    public $application;
    public $revisionMessage;

    public function __construct(Application $application, string $revisionMessage)
    {
        $this->application = $application;
        $this->revisionMessage = $revisionMessage;
    }

    public function build()
    {
        return $this->subject('Žiadosť o úpravu prihlášky #' . $this->application->id)
                    ->html("
                        <h2>Dobrý deň,</h2>
                        <p>Vaša prihláška <strong>#{$this->application->id}</strong> bola administrátorom vrátená na doplnenie údajov.</p>
                        <p><strong>Dôvod / Čo je potrebné doplniť:</strong></p>
                        <blockquote style='background: #f4f4f4; padding: 15px; border-left: 4px solid #3b82f6;'>
                            " . nl2br(e($this->revisionMessage)) . "
                        </blockquote>
                        <p>Prosím, prihláste sa do systému a upravte požadované polia čo najskôr.</p>
                    ");
    }
}