<?php

declare(strict_types=1);

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class EcommerceOrderNotificationMail extends Mailable
{
    use Queueable, SerializesModels;

    public string $emailSubject;
    public string $emailHtmlContent;

    /**
     * Crear una nueva instancia del mailable.
     *
     * @param string $emailSubject Asunto del correo electrónico
     * @param string $emailHtmlContent Contenido HTML procesado con shortcodes
     */
    public function __construct(string $emailSubject, string $emailHtmlContent)
    {
        $this->emailSubject = $emailSubject;
        $this->emailHtmlContent = $emailHtmlContent;
    }

    /**
     * Construir el mensaje.
     */
    public function build(): self
    {
        return $this->subject($this->emailSubject)
            ->html($this->emailHtmlContent);
    }
}
