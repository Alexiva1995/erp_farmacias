<?php

namespace App\Services\Invoices;

use App\Models\DiscountRule;
use App\Models\ExchangeRate;
use App\Models\Invoice;
use App\Models\InvoiceReturn;
use App\Models\PaymentRule;
use App\Models\ProductLot;
use App\Models\SupplierDiscount;
use Carbon\Carbon;
use Exception;
use Illuminate\Support\Facades\DB;

class InvoiceActionService
{
    public function createInvoice(array $data): Invoice
    {
        return DB::transaction(function () use ($data) {
            $totalUSD = $this->calculateTotalUSD($data);

            $invoiceData = [
                'supplier_id' => $data['supplier_id'],
                'invoice_number' => $data['invoice_number'],
                'control_number' => $data['control_number'],
                'exp_date' => $data['exp_date'],
                'payment_date' => $data['payment_date'] ?? null,
                'received_date' => $data['received_date'],
                'exempt_amount' => $data['exempt_amount'] ?? 0,
                'taxable_base' => $data['taxable_base'] ?? 0,
                'tax_amount' => $data['tax_amount'] ?? 0,
                'exchange_rate' => $data['exchange_rate'],
                'total_amount' => $data['total_amount'],
                'total_usd' => $totalUSD,
                'currency' => $data['currency'],
                'discount_rule_id' => $data['discount_rule_id'] ?? null,
                'status' => 'loaded',
                'registered_by' => 1,
                'uploaded_by' => 1
            ];

            return Invoice::create($invoiceData);
        });
    }

    public function deleteInvoice(Invoice $invoice): void
    {
        if ($invoice->payments()->exists()) {
            throw new Exception("No se puede eliminar una factura que ya tiene pagos registrados.");
        }

        DB::transaction(function () use ($invoice) {
            $invoice->delete();
        });
    }

    public function updateInvoiceData(Invoice $invoice, array $data): Invoice
    {
        return DB::transaction(function () use ($invoice, $data) {
            $totalUSD = $this->calculateTotalUSD($data);

            $invoiceData = [
                'supplier_id' => $data['supplier_id'],
                'invoice_number' => $data['invoice_number'],
                'control_number' => $data['control_number'],
                'exp_date' => $data['exp_date'],
                'payment_date' => $data['payment_date'] ?? null,
                'received_date' => $data['received_date'],
                'exempt_amount' => $data['exempt_amount'] ?? 0,
                'taxable_base' => $data['taxable_base'] ?? 0,
                'tax_amount' => $data['tax_amount'] ?? 0,
                'exchange_rate' => $data['exchange_rate'],
                'total_amount' => $data['total_amount'],
                'total_usd' => $totalUSD,
                'currency' => $data['currency'],
                'discount_rule_id' => $data['discount_rule_id'] ?? null,
            ];

            $invoice->update($invoiceData);

            return $invoice->fresh(['details.product', 'supplier']);
        });
    }

    public function updateInvoice(Invoice $invoice, array $data): Invoice
    {
        return DB::transaction(function () use ($invoice, $data) {
            $mergedInvoiceData = array_merge($invoice->toArray(), $data['invoice']);
            $totalUSD = $this->calculateTotalUSD($mergedInvoiceData);
            $updateData = array_merge($data['invoice'], [
                'total_usd' => $totalUSD,
                'status' => 'to_order'
            ]);

            $invoice->update($updateData);
            $invoice->details()->delete();
            $currency = $mergedInvoiceData['currency'];
            $rate = (float) ($mergedInvoiceData['exchange_rate'] ?? 0);

            if ($currency !== 'USD' && $rate <= 0) {
                throw new Exception("La tasa de cambio para la moneda {$currency} debe ser mayor a 0.");
            }

            foreach ($data['details'] as $detail) {
                if ($detail['quantity'] <= 0) {
                    continue;
                }

                $unitCostInInvoiceCurrency = (float) $detail['unit_cost'];
                $unitCostUSD = $unitCostInInvoiceCurrency;

                if ($currency !== 'USD') {
                    $unitCostUSD = round($unitCostInInvoiceCurrency / $rate, 2);
                }

                $totalCostUSD = round($detail['quantity'] * $unitCostUSD, 2);

                $invoice->details()->create([
                    'product_id' => $detail['product']['id'],
                    'quantity' => $detail['quantity'],
                    'unit_cost' => $unitCostUSD,
                    'total_cost' => $totalCostUSD,
                    'lot_number' => $detail['lot_number'],
                    'expiration_date' => $detail['expiration_date'],
                    'location' => $detail['location'],
                ]);
            }

            return $invoice->fresh(['details.product', 'supplier']);
        });
    }

    public function approveInvoice(Invoice $invoice, array $data): Invoice
    {
        if ($invoice->status !== 'to_order') {
            throw new Exception("Solo se pueden aprobar facturas en estado 'to_order'.");
        }

        return DB::transaction(function () use ($invoice, $data) {

            $returnItemIds = $data['return_item_ids'] ?? [];

            $supplierDiscount = null;
            $paymentRule = null;

            if (!empty($data['supplier_discount_id'])) {
                $supplierDiscount = SupplierDiscount::findOrFail($data['supplier_discount_id']);
            }

            if (!empty($data['payment_rule_id'])) {
                $paymentRule = PaymentRule::findOrFail($data['payment_rule_id']);
            }

            foreach ($invoice->details as $detail) {

                if (in_array($detail->id, $returnItemIds)) {
                    $this->createInvoiceReturn($detail, $invoice);
                    continue;
                }

                $finalUnitCost = $detail->unit_cost;
                $totalDiscountPercentage = 0;

                if ($supplierDiscount) {
                    $totalDiscountPercentage += $supplierDiscount->discount_percentage;
                }

                if ($paymentRule) {
                    $totalDiscountPercentage += $paymentRule->discount_percentage;
                }

                if ($totalDiscountPercentage > 0) {
                    $discountAmount = ($finalUnitCost * $totalDiscountPercentage) / 100;
                    $finalUnitCost = $finalUnitCost - $discountAmount;
                }

                $this->createProductLot($detail, $finalUnitCost, $invoice);
            }

            $updateData = [
                'status' => 'ordered'
            ];
            $invoice->update($updateData);
            return $invoice->fresh(['details.product', 'supplier']);
        });
    }

    public function rejectInvoice(Invoice $invoice, string $reason): Invoice
    {
        if ($invoice->status !== 'to_order') {
            throw new Exception("Solo se pueden rechazar facturas en estado 'to_order'.");
        }

        return DB::transaction(function () use ($invoice, $reason) {

            $invoice->update([
                'status' => 'loaded'
            ]);

            return $invoice->fresh(['details.product', 'supplier']);
        });
    }

    private function createProductLot($detail, float $finalUnitCost, Invoice $invoice): ProductLot
    {
        return ProductLot::create([
            'product_id' => $detail->product_id,
            'supplier_id' => $invoice->supplier_id,
            'lot_number' => $detail->lot_number,
            'expiration_date' => $detail->expiration_date,
            'quantity' => $detail->quantity,
            'location' => $detail->location,
            'unit_cost' => $finalUnitCost,
        ]);
    }

    private function createInvoiceReturn($detail, Invoice $invoice): InvoiceReturn
    {
        $amountRefunded = $detail->quantity * $detail->unit_cost;

        return InvoiceReturn::create([
            'invoice_id' => $invoice->id,
            'product_id' => $detail->product_id,
            'quantity' => $detail->quantity,
            'amount_refunded' => $amountRefunded,
            'return_date' => Carbon::today(),
        ]);
    }

    /**
     * Calcula el total en USD usando la tasa de cambio ingresada por el usuario
     */
    private function calculateTotalUSD(array $data): float
    {
        $currency = $data['currency'];
        $totalAmount = $data['total_amount'];

        if ($currency === 'USD') {
            return $totalAmount;
        }

        $rate = $data['exchange_rate'] ?? 1;

        if ($rate <= 0) {
            throw new Exception("La tasa de cambio debe ser mayor a 0");
        }

        return round($totalAmount / $rate, 2);
    }

    /**
     * Mantuve este método por si se necesita en otras partes del sistema
     * donde sí se requiera conversión completa a USD
     */
    private function convertInvoiceDataToUSD(array $data): array
    {
        $currency = $data['currency'];

        if ($currency === 'USD') {
            $data['total_usd'] = $data['total_amount'];
            return $data;
        }

        $rate = $this->getExchangeRateForCurrency($currency);

        $fieldsToConvert = [
            'exempt_amount',
            'taxable_base',
            'tax_amount',
            'total_amount'
        ];

        foreach ($fieldsToConvert as $field) {
            if (isset($data[$field])) {
                $data[$field] = round($data[$field] / $rate, 2);
            }
        }

        $data['total_usd'] = $data['total_amount'];

        return $data;
    }

    private function getExchangeRateForCurrency(string $currencyCode): float
    {
        if ($currencyCode === 'USD') {
            return 1.0;
        }

        $mappedCode = ['Bs' => 'BS'][$currencyCode] ?? $currencyCode;

        $exchangeRate = ExchangeRate::where('currency_code', $mappedCode)->first();

        if (!$exchangeRate) {
            throw new Exception("Tipo de cambio no configurado en el sistema para la moneda: {$mappedCode}");
        }

        return (float) $exchangeRate->rate;
    }
}
