<?php

namespace App\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * Correo con el enlace para crear una contraseña nueva.
 */
class EnlaceRecuperacion extends Mailable
{
    public function __construct(
        public readonly string $url,
        public readonly int $minutos,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Cambia tu contraseña de SIDEANFECA');
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.enlace-recuperacion',
            text: 'emails.enlace-recuperacion-texto',
        );
    }
}
