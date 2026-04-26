<?php

namespace App\Services\Invoices;

use App\Models\AutoOrder;
use App\Models\AutoOrderDetail;
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
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class InvoiceActionService
{
    public function createInvoice(array $data): Invoice
    {
        return DB::transaction(function () use ($data) {
            $totalUSD = $this->calculateTotalUSD($data);
            $autoOrder = AutoOrder::where('supplier_id', $data['supplier_id'])
                ->where('status', 0)
                ->select(['id'])
                ->first();

            $invoiceData = [
                'auto_order_id' => $autoOrder->id ?? null,
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
                'exchange_rate' => $data['currency'] === 'USD' ? 1 : $data['exchange_rate'],
                'total_amount' => $data['total_amount'],
                'total_usd' => $totalUSD,
                'currency' => $data['currency'],
                'discount_rule_id' => $data['discount_rule_id'] ?? null,
                'status' => 'pending',
                'registered_by' => Auth::id(),
                'uploaded_by' => Auth::id(),
                'status_payment' => 0,
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
                'created_invoice_date' => $data['created_invoice_date'],
            ];

            $invoice->update($invoiceData);

            return $invoice->fresh(['details.product', 'supplier']);
        });
    }
    public function saveInvoiceDetails(Invoice $invoice, array $data): Invoice
    {
        Log::info('Starts saveInvoiceDetails', ['invoice_id' => $invoice->id, 'details_count' => count($data['details'])]);

        if ($invoice->status !== 'pending') {
            throw new Exception("Solo se puede guardar el progreso en facturas con estado 'pendiente'.");
        }

        return DB::transaction(function () use ($invoice, $data) {
            $invoice->update($data['invoice']);

            $generalDiscountId = $data['invoice']['supplier_discount_id'] ?? null;
            $discountPercentage = 0;

            if ($generalDiscountId) {
                $discountRecord = DB::table('supplier_discounts')
                    ->where('id', $generalDiscountId)
                    ->first();
                if ($discountRecord) {
                    $discountPercentage = (float) $discountRecord->discount_percentage;
                }
            }

            $productIds = collect($data['details'])
                ->pluck('product.id')
                ->filter()
                ->unique()
                ->values()
                ->toArray();

            $autoOrderDetailMapping = [];

            if (!empty($productIds) && !empty($invoice->autoOrder)) {
                $autoOrderDetails = DB::table('auto_order_details')
                    ->join('product_suppliers', 'auto_order_details.product_suppliers_id', '=', 'product_suppliers.id')
                    ->where('auto_order_details.order_id', $invoice->autoOrder->id)
                    ->whereIn('product_suppliers.product_id', $productIds)
                    ->select(
                        'auto_order_details.id',
                        'product_suppliers.product_id',
                        'auto_order_details.received'
                    )
                    ->get()
                    ->keyBy('product_id')
                    ->toArray();

                foreach ($autoOrderDetails as $detail) {
                    $autoOrderDetailMapping[$detail->product_id] = $detail;
                }
            }

            $invoice->details()->delete();
            $invoice->returns()->delete();
            $autoOrderDetailsToUpdate = [];

            $currency = $invoice->currency;
            $rate = (float) ($invoice->exchange_rate ?? 0);

            if ($currency !== 'USD' && $rate <= 0) {
                throw new Exception("La tasa de cambio para la moneda {$currency} debe ser mayor a 0.");
            }

            foreach ($data['details'] as $index => $detail) {
                if ($detail['quantity'] <= 0) {
                    Log::warning('Skipping detail with quantity <= 0', ['detail' => $detail]);
                    continue;
                }

                $productId = $detail['product']['id'];
                Log::info('Processing detail', ['product_id' => $productId, 'quantity' => $detail['quantity']]);
                $quantity = (int) $detail['quantity'];
                $unitCostInInvoiceCurrency = (float) $detail['unit_cost'];
                $taxEnabled = isset($detail['tax_enabled']) && $detail['tax_enabled'] === true;
                $displayOrder = isset($detail['display_order']) ? (int) $detail['display_order'] : $index;

                $autoOrderDetail = $autoOrderDetailMapping[$productId] ?? null;
                $autoOrderDetailId = $autoOrderDetail ? $autoOrderDetail->id : null;

                // $totalCostInInvoiceCurrency = $quantity * $unitCostInInvoiceCurrency;

                $subtotal = $quantity * $unitCostInInvoiceCurrency;
                $discountAmount = $subtotal * ($discountPercentage / 100);
                $totalAfterDiscount = $subtotal - $discountAmount;

                /*if ($taxEnabled) {
                    $totalCostInInvoiceCurrency = $totalAfterDiscount * 1.16;
                }*/
                $totalCostInInvoiceCurrency = $taxEnabled ? $totalAfterDiscount * 1.16 : $totalAfterDiscount;
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
                        'auto_order_details_id' => $autoOrderDetailId,
                        'supplier_discount_percentage' => $discountPercentage,
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
                        'auto_order_details_id' => $autoOrderDetailId,
                        'supplier_discount_percentage' => $discountPercentage,
                        'display_order' => $displayOrder,
                    ]);

                    Log::info('Detail created', ['product_id' => $productId, 'auto_order_detail_id' => $autoOrderDetailId]);

                    if ($autoOrderDetailId) {
                        $autoOrderDetailsToUpdate[$autoOrderDetailId] = $autoOrderDetailId;
                    }
                }
            }

            if (!empty($autoOrderDetailsToUpdate) && !empty($invoice->autoOrder)) {
                DB::table('auto_order_details')
                    ->whereIn('id', array_values($autoOrderDetailsToUpdate))
                    ->update([
                        'received' => 1,
                        'status' => 1
                    ]);

                $allDetailsCount = DB::table('auto_order_details')
                    ->where('order_id', $invoice->autoOrder->id)
                    ->count();

                $completedDetailsCount = DB::table('auto_order_details')
                    ->where('order_id', $invoice->autoOrder->id)
                    ->where('status', 1)
                    ->count();

                if ($allDetailsCount > 0 && $allDetailsCount === $completedDetailsCount) {
                    DB::table('auto_orders')
                        ->where('id', $invoice->autoOrder->id)
                        ->update(['status' => 1]);
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

        return DB::transaction(function () use ($invoice) {
            $invoice->update([
                'status' => 'loaded',
                'loaded_by' => Auth::id()
            ]);

            // Activar productos suspendidos (is_deleted = 1) al finalizar la carga
            foreach ($invoice->details as $detail) {
                if ($detail->product && $detail->product->is_deleted) {
                    $detail->product->update(['is_deleted' => 0]);
                }
            }

            return $invoice->fresh(['details.product', 'supplier']);
        });
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

            // Activar productos pendientes (is_deleted = 1)
            foreach ($invoice->details as $detail) {
                if ($detail->product && $detail->product->is_deleted) {
                    $detail->product->update(['is_deleted' => 0]);
                }
            }

            $invoice->update($updateData);
            $invoice->refresh();

            // Cargar detalles con productos y rentabilidad
            $invoice->load(['details.product.profitability']);

            // ÚNICO punto donde se crean movimientos de inventario (purchase) por factura.
            // No se crean al cargar (loaded) ni al ordenar/archivar (ordered); solo al aprobar (loaded → to_order).
            \App\Observers\ProductObserver::handleInvoiceMovement($invoice);

            // Crear lotes al aprobar (sin ubicación todavía, se actualizará después)
            foreach ($invoice->details as $detail) {
                // Convertir unit_cost a USD antes de crear el lote
                $unitCostInInvoiceCurrency = $detail->unit_cost;
                $unitCostInUSD = $invoice->currency === 'USD'
                    ? $unitCostInInvoiceCurrency
                    : ($unitCostInInvoiceCurrency / ($invoice->exchange_rate ?? 1));

                // Crear lote sin ubicación (se actualizará en updateInvoiceLocations)
                $productLot = $this->createProductLot($detail, $unitCostInUSD, $invoice);

                // Actualizar el movimiento existente con el product_lot_id
                \App\Models\InventoryMovement::where('invoice_id', $invoice->id)
                    ->where('product_id', $detail->product_id)
                    ->whereNull('product_lot_id')
                    ->where('movement_type', 'purchase')
                    ->where('quantity', $detail->quantity)
                    ->update(['product_lot_id' => $productLot->id]);
            }

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
            'location' => $detail->location ?? null, // Puede ser null, se actualizará después
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
                    // Actualizar ubicación en el detalle
                    $detail->update(['location' => $detailData['location']]);

                    // Actualizar ubicación en el lote correspondiente
                    // Buscar el lote por producto, número de lote y fecha de expiración
                    $productLot = ProductLot::where('product_id', $detail->product_id)
                        ->where('lot_number', $detail->lot_number)
                        ->where('expiration_date', $detail->expiration_date)
                        ->where('supplier_id', $invoice->supplier_id)
                        ->first();

                    if ($productLot) {
                        $productLot->update(['location' => $detailData['location']]);
                    }
                }
            }

            $invoice->update([
                'status' => 'ordered',
                'ordered_by' => Auth::id()
            ]);

            return $invoice->fresh(['details.product', 'supplier']);
        });
    }

    public function updateToPendingStatus(Invoice $invoice): array
    {
        if ($invoice->status === 'pending') {
            \Log::warning('Attempt to set already pending invoice to pending', ['invoice_id' => $invoice->id]);
            return ['status' => false, 'message' => null];
        }

        try {
            DB::transaction(function () use ($invoice) {
                $detailIds = $invoice->details()->pluck('auto_order_details_id')->filter();

                if ($detailIds->isNotEmpty()) {
                    AutoOrderDetail::whereIn('id', $detailIds)
                        ->update(['status' => 0, 'received' => null]);
                }

                if ($invoice->autoOrder) {
                    $orderId = $invoice->autoOrder->id;
                    $counts = DB::table('auto_order_details')
                        ->where('order_id', $orderId)
                        ->selectRaw('COUNT(*) as total, SUM(CASE WHEN status = 1 THEN 1 ELSE 0 END) as completed')
                        ->first();

                    if ($counts->total > 0 && $counts->total === $counts->completed) {
                        DB::table('auto_orders')
                            ->where('id', $orderId)
                            ->update(['status' => 1]);
                    }
                }

                $invoice->update(['status' => 'pending']);
            });

            return ['status' => true, 'message' => null];
        } catch (Exception $e) {
            \Log::error('Return invoice to pending failed', [
                'invoice_id' => $invoice->id,
                'error' => $e->getMessage()
            ]);
            return ['status' => false, 'message' => null];
        }
    public function uploadInvoicePhoto(Invoice $invoice, $file): Invoice
    {
        return DB::transaction(function () use ($invoice, $file) {
            // Eliminar foto anterior si existe
            if ($invoice->invoice_photo) {
                \Illuminate\Support\Facades\Storage::disk('public')->delete($invoice->invoice_photo);
            }

            $path = $file->store('invoice_photos', 'public');

            $invoice->update(['invoice_photo' => $path]);

            return $invoice->fresh();
        });
    }
}
