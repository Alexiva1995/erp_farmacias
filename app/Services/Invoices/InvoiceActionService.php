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
            $convertedData = $this->convertInvoiceDataToUSD($data);

            $invoiceData = [
                'supplier_id' => $convertedData['supplier_id'],
                'invoice_number' => $convertedData['invoice_number'],
                'control_number' => $convertedData['control_number'],
                'exp_date' => $convertedData['exp_date'],
                'payment_date' => $convertedData['payment_date'] ?? null,
                'received_date' => $convertedData['received_date'],
                'exempt_amount' => $convertedData['exempt_amount'] ?? 0,
                'taxable_base' => $convertedData['taxable_base'] ?? 0,
                'tax_amount' => $convertedData['tax_amount'] ?? 0,
                'exchange_rate' => $convertedData['exchange_rate'],
                'total_amount' => $convertedData['total_amount'],
                'total_usd' => $convertedData['total_amount'],
                'currency' => $convertedData['currency'],
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
            $convertedData = $this->convertInvoiceDataToUSD($data);

            $invoiceData = [
                'supplier_id' => $convertedData['supplier_id'],
                'invoice_number' => $convertedData['invoice_number'],
                'control_number' => $convertedData['control_number'],
                'exp_date' => $convertedData['exp_date'],
                'payment_date' => $convertedData['payment_date'] ?? null,
                'received_date' => $convertedData['received_date'],
                'exempt_amount' => $convertedData['exempt_amount'] ?? 0,
                'taxable_base' => $convertedData['taxable_base'] ?? 0,
                'tax_amount' => $convertedData['tax_amount'] ?? 0,
                'exchange_rate' => $convertedData['exchange_rate'],
                'total_amount' => $convertedData['total_amount'],
                'total_usd' => $convertedData['total_amount'],
                'currency' => $convertedData['currency'],
                'discount_rule_id' => $convertedData['discount_rule_id'] ?? null,
            ];

            $invoice->update($invoiceData);

            return $invoice->fresh(['details.product', 'supplier']);
        });
    }

    public function updateInvoice(Invoice $invoice, array $data): Invoice
    {
        return DB::transaction(function () use ($invoice, $data) {
            $convertedInvoiceData = $this->convertInvoiceDataToUSD($data['invoice']);
            $convertedInvoiceData['status'] = 'to_order';
            $invoice->update($convertedInvoiceData);

            $invoice->details()->delete();

            $currency = $data['invoice']['currency'];
            $rate = ($currency !== 'USD') ? $this->getExchangeRateForCurrency($currency) : 1;

            foreach ($data['details'] as $detail) {
                if ($detail['quantity'] > 0) {
                    $unitCostUSD = $detail['unit_cost'] / $rate;

                    $invoice->details()->create([
                        'product_id' => $detail['product']['id'],
                        'quantity' => $detail['quantity'],
                        'unit_cost' => $unitCostUSD,
                        'total_cost' => $detail['quantity'] * $unitCostUSD,
                        'lot_number' => $detail['lot_number'],
                        'expiration_date' => $detail['expiration_date'],
                        'location' => $detail['location'],
                    ]);
                }
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

            $invoice->update([
                'status' => 'ordered'
            ]);

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
