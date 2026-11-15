<?php

declare(strict_types=1);

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class CobroMensualidadMail extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * Datos del cobro.
     *
     * @var array<string, mixed>
     */
    public array $data;

    /**
     * Constructor del Mailable.
     *
     * @param array<string, mixed> $data
     */
    public function __construct(array $data)
    {
        $this->data = $data;
    }

    /**
     * Configuración del sobre del correo: asunto.
     */
    public function envelope(): Envelope
    {
        $siteLabel = $this->data['site_name'] ?? $this->data['site'] ?? 'TOVA';

        return new Envelope(
            subject: '🔔 Renovación de Mensualidad — ' . $siteLabel,
        );
    }

    /**
     * Contenido del correo: vista Blade.
     */
    public function content(): Content
    {
        return new Content(
            view: 'emails.cobro-mensualidad',
        );
    }

    /**
     * Adjuntos.
     *
     * @return array<int, \Illuminate\Mail\Mailables\Attachment>
     */
    public function attachments(): array
    {
        return [];
    }
}
