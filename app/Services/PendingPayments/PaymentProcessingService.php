<?php

namespace App\Services\PendingPayments;

use App\Models\Invoice;
use App\Models\ExchangeRate;
use App\Models\InvoicePayment;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PaymentProcessingService
{
    /**
     * Validar datos de pago
     */
    public function validatePaymentData(array $data): array
    {
        $errors = [];

        if (empty($data['invoice_ids']) || !is_array($data['invoice_ids'])) {
            $errors[] = 'Los IDs de facturas son requeridos';
        }

        if (empty($data['payment_type']) || !in_array($data['payment_type'], ['full', 'partial'])) {
            $errors[] = 'El tipo de pago es requerido y debe ser "full" o "partial"';
        }

        if (empty($data['payment_currency']) || !in_array($data['payment_currency'], ['VES', 'USD', 'COP'])) {
            $errors[] = 'La moneda de pago es requerida';
        }

        if (empty($data['payment_amount']) || $data['payment_amount'] <= 0) {
            $errors[] = 'El monto de pago debe ser mayor a 0';
        }

        if (empty($data['payment_date'])) {
            $errors[] = 'La fecha de pago es requerida';
        }

        return $errors;
    }

    /**
     * Verificar que las facturas no estén ya pagadas
     */
    public function validateInvoicesNotPaid(array $invoiceIds): array
    {
        $invoices = Invoice::with(['supplier'])
            ->whereIn('id', $invoiceIds)
            ->get();

        $errors = [];
        foreach ($invoices as $invoice) {
            if ($invoice->status_payment === 1) {
                $errors[] = "La factura {$invoice->invoice_number} ya está pagada";
            }
        }

        return $errors;
    }

    /**
     * Verificar pagos duplicados (versión mejorada para pagos parciales)
     */
    public function checkDuplicatePayment(array $data): ?string
    {
        // CORRECCIÓN: Para pagos parciales, ser más permisivo
        // Solo considerar duplicado si es exactamente el mismo pago con referencia

        // Si es pago parcial, permitir múltiples pagos con el mismo monto
        if ($data['payment_type'] === 'partial') {
            // Solo bloquear si tiene la misma referencia Y es exactamente el mismo pago
            if (!empty($data['reference'])) {
                $duplicatePayment = InvoicePayment::where('amount', $data['payment_amount'])
                    ->where('payment_date', $data['payment_date'])
                    ->where('payment_method', $data['payment_currency'])
                    ->where('reference', $data['reference'])
                    ->whereHas('invoices', function ($query) use ($data) {
                        $query->whereIn('id', $data['invoice_ids']);
                    })
                    ->first();

                if ($duplicatePayment) {
                    return 'Ya existe un pago idéntico registrado para estas facturas con la misma referencia. Por favor, usa una referencia diferente.';
                }
            }
            // Si no hay referencia, permitir el pago (pagos parciales legítimos)
            return null;
        }

        // Para pagos completos, mantener validación estricta
        $duplicatePayment = InvoicePayment::where('amount', $data['payment_amount'])
            ->where('payment_date', $data['payment_date'])
            ->where('payment_method', $data['payment_currency'])
            ->whereHas('invoices', function ($query) use ($data) {
                $query->whereIn('id', $data['invoice_ids']);
            })
            ->first();

        if ($duplicatePayment) {
            return 'Ya existe un pago idéntico registrado para estas facturas. Por favor, verifica los datos.';
        }

        return null;
    }

    /**
     * Calcular monto en USD según la moneda de pago
     */
    public function calculateAmountInUSD(float $amount, string $currency): float
    {
        if ($currency === 'USD') {
            return $amount;
        }

        $currencyCode = $this->normalizeCurrencyCode($currency);
        $exchangeRate = ExchangeRate::where('currency_code', $currencyCode)->first();

        if (!$exchangeRate) {
            throw new \Exception('No se encontró tasa de cambio para la moneda seleccionada');
        }

        return round($amount / $exchangeRate->rate, 2);
    }

    /**
     * Determinar estado de pago considerando solo pagos anteriores
     */
    public function determinePaymentStatus(array $invoiceIds, float $currentPaymentUSD, float $totalAmountUSD): int
    {
        // Obtener el total pagado anteriormente en USD para estas facturas
        // EXCLUIR el pago actual usando created_at < now() para evitar contar el pago que se está procesando
        $payments = InvoicePayment::whereHas('invoices', function ($query) use ($invoiceIds) {
            $query->whereIn('id', $invoiceIds);
        })
            ->where('created_at', '<', now())
            ->get();

        $previousPaymentsUSD = 0;
        foreach ($payments as $payment) {
            if ($payment->payment_method === 'USD') {
                $previousPaymentsUSD += $payment->amount;
            } else {
                $exchangeRate = ExchangeRate::where('currency_code', $payment->payment_method)->first();
                if ($exchangeRate) {
                    $previousPaymentsUSD += round($payment->amount / $exchangeRate->rate, 2);
                }
            }
        }

        // Calcular el total pagado incluyendo el pago actual
        $totalPaidUSD = $previousPaymentsUSD + $currentPaymentUSD;

        // Usar tolerancia muy pequeña para evitar problemas de redondeo
        $tolerance = 0.01;

        // CORRECCIÓN: Si el pago actual es menor al total, siempre es parcial (0)
        // Solo es completo (1) si el total pagado >= total de la factura
        if ($totalPaidUSD >= ($totalAmountUSD - $tolerance)) {
            return 1; // paid
        } else {
            return 0; // pending/partial
        }
    }

    /**
     * Crear registro de pago
     */
    public function createPayment(array $data): InvoicePayment
    {
        $normalizedCurrency = $this->normalizeCurrencyCode($data['payment_currency']);

        return InvoicePayment::create([
            'payment_date' => $data['payment_date'],
            'amount' => $data['payment_amount'],
            'payment_method' => $normalizedCurrency,
            'reference' => $data['reference'] ?? null,
            'status' => 'paid',
            'payment_by' => 1, // TODO: Obtener ID del usuario autenticado
            'photo_url' => $data['photo_url'] ?? null,
        ]);
    }

    /**
     * Crear relaciones en tabla pivot
     */
    public function createPaymentInvoiceRelations(int $paymentId, array $invoiceIds): void
    {
        foreach ($invoiceIds as $invoiceId) {
            DB::table('invoice_payment_invoice')->insert([
                'payment_id' => $paymentId,
                'invoice_id' => $invoiceId,
            ]);
        }
    }

    /**
     * Actualizar estado de facturas
     */
    public function updateInvoicesStatus(array $invoiceIds, int $paymentStatus, string $paymentDate): void
    {
        Invoice::whereIn('id', $invoiceIds)->update([
            'status' => 'to_order', // Siempre mantener en to_order para que aparezca en Por Pagar
            'status_payment' => $paymentStatus,
            'payment_date' => $paymentDate,
            'updated_at' => now(),
        ]);
    }

    /**
     * Crear registro en expenses
     */
    public function createExpense(array $invoices, InvoicePayment $payment, float $amountUSD, bool $iva = false): void
    {
        // Crear o obtener categoría
        $category = ExpenseCategory::firstOrCreate([
            'name' => 'Pagos de Facturas'
        ]);

        // Crear expense
        Expense::create([
            'name' => "Pago Factura # {$invoices[0]->invoice_number} Proveedor {$invoices[0]->supplier->name}",
            'category_id' => $category->id,
            'amount' => $payment->amount,
            'amount_usd' => $amountUSD,
            'currency' => $payment->payment_method,
            'expense_date' => $payment->payment_date,
            'user_id' => $payment->payment_by,
            'has_invoice' => true,
            'is_deductible' => true,
            'iva' => $iva
        ]);
    }

    /**
     * Normalizar códigos de moneda para consistencia
     */
    private function normalizeCurrencyCode(string $currency): string
    {
        $normalized = strtoupper(trim($currency));

        $currencyMap = [
            'BS' => 'BS',
            'Bs' => 'BS',
            'VES' => 'BS',
            'USD' => 'USD',
            'COP' => 'COP',
        ];

        return $currencyMap[$normalized] ?? $normalized;
    }
}
