<?php

namespace App\Services\Invoices;

use App\Models\DiscountRule;
use App\Models\ExchangeRate;
use App\Models\Invoice;
use App\Models\InvoiceDetail;
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
                'created_invoice_date' => $data['created_invoice_date'],
                'exempt_amount' => $data['exempt_amount'] ?? 0,
                'taxable_base' => $data['taxable_base'] ?? 0,
                'tax_amount' => $data['tax_amount'] ?? 0,
                'exchange_rate' => $data['exchange_rate'],
                'total_amount' => $data['total_amount'],
                'total_usd' => $totalUSD,
                'currency' => $data['currency'],
                'discount_rule_id' => $data['discount_rule_id'] ?? null,
                'status' => 'pending',
                'registered_by' => 1,
                'uploaded_by' => 1,
                'status_payment' => 'pending',
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
        if (!in_array($invoice->status, ['pending', 'loaded'])) {
            throw new Exception("Solo se pueden actualizar los datos de una factura en estado 'pendiente' o 'cargada'.");
        }

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
    public function saveInvoiceDetails(Invoice $invoice, array $data): Invoice
    {
        if ($invoice->status !== 'pending') {
            throw new Exception("Solo se puede guardar el progreso en facturas con estado 'pendiente'.");
        }

        return DB::transaction(function () use ($invoice, $data) {
            $invoice->update($data['invoice']);

            $invoice->details()->delete();
            $invoice->returns()->delete();

            $currency = $invoice->currency;
            $rate = (float) ($invoice->exchange_rate ?? 0);

            if ($currency !== 'USD' && $rate <= 0) {
                throw new Exception("La tasa de cambio para la moneda {$currency} debe ser mayor a 0.");
            }

            foreach ($data['details'] as $detail) {
                if ($detail['quantity'] <= 0) {
                    continue;
                }

                $productId = $detail['product']['id'];
                $quantity = (int) $detail['quantity'];
                $unitCostInInvoiceCurrency = (float) $detail['unit_cost'];
                $taxEnabled = isset($detail['tax_enabled']) && $detail['tax_enabled'] === true;

                $totalCostInInvoiceCurrency = $quantity * $unitCostInInvoiceCurrency;
                if ($taxEnabled) {
                    $totalCostInInvoiceCurrency = $totalCostInInvoiceCurrency * 1.16;
                }
                $totalCostInInvoiceCurrency = round($totalCostInInvoiceCurrency, 2);

                if (isset($detail['is_return']) && $detail['is_return'] === true) {
                    $refundAmount = $totalCostInInvoiceCurrency;

                    InvoiceReturn::create([
                        'invoice_id' => $invoice->id,
                        'product_id' => $productId,
                        'quantity' => $quantity,
                        'amount_refunded' => $refundAmount,
                        'return_date' => Carbon::today(),
                        'lot_number' => $detail['lot_number'] ?? null,
                        'expiration_date' => $detail['expiration_date'] ?? null,
                    ]);
                } else {
                    $invoice->details()->create([
                        'product_id' => $productId,
                        'quantity' => $quantity,
                        'unit_cost' => $unitCostInInvoiceCurrency,
                        'total_cost' => $totalCostInInvoiceCurrency,
                        'lot_number' => $detail['lot_number'],
                        'expiration_date' => $detail['expiration_date'],
                        'location' => $detail['location'],
                        'tax_enabled' => $taxEnabled,
                    ]);
                }
            }

            return $invoice->fresh(['details.product', 'supplier', 'returns.product']);
        });
    }
    public function finalizeInvoice(Invoice $invoice): Invoice
    {
        if ($invoice->status !== 'pending') {
            throw new Exception("Solo se pueden finalizar facturas en estado 'pendiente'.");
        }

        $invoice->update(['status' => 'loaded']);

        return $invoice->fresh(['details.product', 'supplier']);
    }
    public function approveInvoice(Invoice $invoice, array $data): Invoice
    {
        if ($invoice->status !== 'loaded') {
            throw new Exception("Solo se pueden aprobar facturas en estado 'cargada'.");
        }

        return DB::transaction(function () use ($invoice, $data) {

            $updateData = ['status' => 'to_order'];
            $paymentRule = null;

            if (!empty($data['payment_rule_id'])) {
                $paymentRule = PaymentRule::findOrFail($data['payment_rule_id']);
                $discountPercentage = $paymentRule->discount_percentage;

                $discountAmount = ($invoice->total_amount * $discountPercentage) / 100;
                $updateData['total_amount_discount'] = $invoice->total_amount - $discountAmount;
                $updateData['payment_rule_id'] = $paymentRule->id;

                foreach ($invoice->details as $detail) {
                    $originalUnitCost = $detail->unit_cost;

                    $discountAmountDetail = ($originalUnitCost * $discountPercentage) / 100;
                    $finalUnitCost = $originalUnitCost - $discountAmountDetail;

                    $detail->update([
                        'unit_cost' => $finalUnitCost,
                        'total_cost' => $finalUnitCost * $detail->quantity,
                    ]);
                }
            }

            $invoice->update($updateData);

            return $invoice->fresh(['details.product', 'supplier']);
        });
    }

    public function rejectInvoice(Invoice $invoice): Invoice
    {
        if ($invoice->status !== 'loaded') {
            throw new Exception("Solo se pueden rechazar facturas en estado 'cargada'.");
        }

        return DB::transaction(function () use ($invoice) {
            $invoice->update([
                'status' => 'pending'
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
    public function updateInvoiceLocations(Invoice $invoice, array $data): Invoice
    {
        if ($invoice->status !== 'to_order') {
            throw new Exception("Solo se pueden ubicar productos en facturas con estado 'ordenada'.");
        }

        return DB::transaction(function () use ($invoice, $data) {
            foreach ($data['details'] as $detailData) {
                $detail = InvoiceDetail::find($detailData['id']);

                if ($detail && $detail->invoice_id === $invoice->id) {
                    $detail->update(['location' => $detailData['location']]);
                    $this->createProductLot($detail, $detail->unit_cost, $invoice);
                }
            }

            $invoice->update(['status' => 'ordered']);
            return $invoice->fresh(['details.product', 'supplier']);
        });
    }
}
