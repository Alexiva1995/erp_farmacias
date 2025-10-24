<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ReporteHistoryCierreCajaMail extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * Create a new message instance.
     */
    public $pdfHistoryContent;
    public $namePDF;

    public function __construct(string $pdfHistoryContent, $nameHistoryPDF)
    {
        $this->pdfHistoryContent = $pdfHistoryContent;
        $this->namePDF = $nameHistoryPDF;
    }

    /**
     * Get the message envelope.
     */
    public function build()
    {
        return $this->subject('Reporte Historico de Cierre Caja')
                    ->view('emails.empty')
                    ->attachData($this->pdfHistoryContent, $this->namePDF, [
                        'mime' => 'application/pdf',
                    ]);
    }
}
