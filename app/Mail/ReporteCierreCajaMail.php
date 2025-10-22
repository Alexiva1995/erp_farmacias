<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Mail\Mailables\Attachment;

class ReporteCierreCajaMail extends Mailable
{
    use Queueable, SerializesModels;

    public $pdfContent;
    public $namePDF;

    public function __construct(string $pdfContent, $namePDF)
    {
        $this->pdfContent = $pdfContent;
        $this->namePDF = $namePDF;
    }

    /**
     * Construye el mensaje.
     *
     * @return $this
     */
    public function build()
    {
        return $this->subject('Reporte Cierre Caja')
                    ->view('emails.empty')
                    ->attachData($this->pdfContent, $this->namePDF, [
                        'mime' => 'application/pdf',
                    ]);
    }
}
