<?php

namespace App\Mail;

use App\Models\GeneralSetting;
use App\Models\Supplier;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;

class SupplierPaymentReceiptMail extends Mailable
{
    use Queueable, SerializesModels;

    public Supplier $supplier;
    public Collection|array $invoices;
    public array $paymentData;
    public ?string $receiptPhotoPath;

    /**
     * Create a new message instance.
     */
    public function __construct(Supplier $supplier, Collection|array $invoices, array $paymentData, ?string $receiptPhotoPath = null)
    {
        $this->supplier = $supplier;
        $this->invoices = $invoices instanceof Collection ? $invoices : collect($invoices);
        $this->paymentData = $paymentData;
        $this->receiptPhotoPath = $receiptPhotoPath;
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        $settings = GeneralSetting::first();
        $pharmacyName = $settings?->app_name ?? config('app.name', 'Farmacia');
        $invoiceNumbers = $this->invoices->pluck('invoice_number')->filter()->take(3)->join(', ');
        $moreCount = $this->invoices->count() > 3 ? ' (+' . ($this->invoices->count() - 3) . ' más)' : '';

        return new Envelope(
            subject: "Comprobante de Pago — Facturas #{$invoiceNumbers}{$moreCount} | {$pharmacyName}",
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        $settings = GeneralSetting::first();
        $pharmacyName = $settings?->app_name ?? config('app.name', 'Farmacia');
        $pharmacyRif = $settings?->app_rif ?? $settings?->rif ?? '';
        $pharmacyAddress = $settings?->address ?? '';
        
        $pharmacyLogo = null;
        if (!empty($settings?->app_logo)) {
            $pharmacyLogo = str_starts_with($settings->app_logo, 'http')
                ? $settings->app_logo
                : url('storage/' . $settings->app_logo);
        }

        $receiptPhotoUrl = null;
        if (!empty($this->receiptPhotoPath)) {
            $receiptPhotoUrl = str_starts_with($this->receiptPhotoPath, 'http')
                ? $this->receiptPhotoPath
                : url('storage/' . $this->receiptPhotoPath);
        }

        $methodLabels = [
            'TRANSFER' => 'Transferencia Bancaria',
            'MOBILE'   => 'Pago Móvil Interbancario',
            'CASH'     => 'Efectivo',
            'CARD'     => 'Tarjeta de Débito / Crédito',
            'BINANCE'  => 'Binance Pay (Cripto)',
            'PAYPAL'   => 'PayPal',
            'CREDIT'   => 'Crédito',
        ];

        $rawMethod = strtoupper($this->paymentData['payment_method'] ?? 'TRANSFER');
        $paymentMethodLabel = $methodLabels[$rawMethod] ?? $rawMethod;

        return new Content(
            view: 'emails.supplier-payment-receipt',
            with: [
                'supplier' => $this->supplier,
                'invoices' => $this->invoices,
                'paymentData' => $this->paymentData,
                'paymentMethodLabel' => $paymentMethodLabel,
                'pharmacyName' => $pharmacyName,
                'pharmacyRif' => $pharmacyRif,
                'pharmacyAddress' => $pharmacyAddress,
                'pharmacyLogo' => $pharmacyLogo,
                'receiptPhotoUrl' => $receiptPhotoUrl,
            ],
        );
    }

    /**
     * Get the attachments for the message.
     *
     * @return array<int, \Illuminate\Mail\Mailables\Attachment>
     */
    public function attachments(): array
    {
        $attachments = [];

        if (!empty($this->receiptPhotoPath)) {
            $localPath = null;
            if (Storage::disk('public')->exists($this->receiptPhotoPath)) {
                $localPath = Storage::disk('public')->path($this->receiptPhotoPath);
            } elseif (file_exists(public_path('storage/' . $this->receiptPhotoPath))) {
                $localPath = public_path('storage/' . $this->receiptPhotoPath);
            }

            if ($localPath && file_exists($localPath)) {
                $attachments[] = Attachment::fromPath($localPath)
                    ->as('Comprobante_Pago_' . ($this->paymentData['reference'] ?? 'Soporte') . '.' . pathinfo($localPath, PATHINFO_EXTENSION));
            }
        }

        return $attachments;
    }
}
