<?php

declare(strict_types=1);

namespace App\Services\Returns;

use App\Enums\ReturnFiscalStatusEnum;
use App\Enums\ReturnRefundMethodEnum;
use App\Models\FiscalCommand;
use App\Models\GeneralSetting;
use App\Models\InventoryMovement;
use App\Models\Order;
use App\Models\OrderDetail;
use App\Models\Product;
use App\Models\ProductLot;
use App\Models\ReturnEntry;
use App\Models\Transaction;
use App\TransactionType;
use Carbon\Carbon;
use Exception;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ReturnsActionService
{
    /**
     * Buscar órdenes completadas elegibles para devolución dentro de la ventana configurada.
     */
    public function searchOrdersReturns(string $searchTerm, array $options = [], ?int $orderId = null): Builder
    {
        $settings = GeneralSetting::first();
        $windowDays = max(1, (int) ($settings?->return_window_days ?? 2));
        $cutoffDate = Carbon::now()->subDays($windowDays);

        $query = Order::query()
            ->where('status', Order::COMPLETED)
            ->with(['client', 'details.product.laboratory', 'returns', 'fiscalHistory']);

        if ($orderId !== null && $orderId > 0) {
            return $query->where('id', $orderId);
        }

        $normalizedSearch = preg_replace('/[^A-Za-z0-9]/', '', $searchTerm);

        $query->where('order_date', '>=', $cutoffDate)
            ->where(function ($q) use ($searchTerm, $normalizedSearch) {
                if (is_numeric($searchTerm) && strlen($searchTerm) < 10) {
                    $q->where('id', (int) $searchTerm);
                }

                $q->orWhereHas('fiscalHistory', function ($sub) use ($searchTerm) {
                    $sub->where('invoice_number', 'like', "%{$searchTerm}%");
                });

                $q->orWhereHas('client', function ($sub) use ($searchTerm, $normalizedSearch) {
                    $sub->where('identification', 'like', "%{$searchTerm}%")
                        ->orWhere('identification', 'like', "%{$normalizedSearch}%")
                        ->orWhereRaw("REPLACE(REPLACE(identification, '.', ''), '-', '') LIKE ?", ["%{$normalizedSearch}%"])
                        ->orWhereRaw("REPLACE(REPLACE(CONCAT(COALESCE(identification_type,''), COALESCE(identification,'')), '-', ''), '.', '') LIKE ?", ["%{$normalizedSearch}%"])
                        ->orWhere('name', 'like', "%{$searchTerm}%")
                        ->orWhere('last_name', 'like', "%{$searchTerm}%")
                        ->orWhereRaw('CONCAT(COALESCE(name,""), " ", COALESCE(last_name,"")) LIKE ?', ["%{$searchTerm}%"]);
                });
            });

        if (!empty($options['sortBy'])) {
            $sortBy = $options['sortBy'];
            $orderBy = ($options['orderBy'] ?? 'desc') === 'asc' ? 'asc' : 'desc';
            $query->orderBy($sortBy, $orderBy);
        } else {
            $query->orderBy('order_date', 'desc');
        }

        return $query;
    }

    /**
     * Obtener lotes disponibles de un producto para distribución en devolución.
     */
    public function getProductLotsForReturn(int $productId): array
    {
        $lots = ProductLot::where('product_id', $productId)
            ->orderBy('expiration_date', 'asc')
            ->orderBy('id', 'asc')
            ->get()
            ->map(function ($lot) {
                return [
                    'id' => $lot->id,
                    'lot_number' => $lot->lot_number,
                    'expiration_date' => $lot->expiration_date ? $lot->expiration_date->format('Y-m-d') : null,
                    'quantity' => $lot->quantity,
                    'location' => $lot->location ?? '',
                    'unit_cost' => $lot->unit_cost,
                    'is_expired' => $lot->expiration_date ? $lot->expiration_date->isPast() : false,
                ];
            });

        return [
            'lots' => $lots,
        ];
    }

    /**
     * Registrar una o varias devoluciones desde el flujo unificado.
     * Si el negocio no requiere aprobación de supervisor (o se solicita aprobación directa con permiso),
     * ejecuta de una vez el reingreso a inventario, el reembolso y la Nota de Crédito fiscal.
     *
     * @return array<string, mixed>
     */
    public function createReturns(array $validated): array
    {
        return DB::transaction(function () use ($validated) {
            $settings = GeneralSetting::first();
            $requireApproval = (bool) ($settings?->require_return_approval ?? true);
            $windowDays = max(1, (int) ($settings?->return_window_days ?? 2));

            /** @var Order $order */
            $order = Order::with(['client', 'details.product', 'fiscalHistory', 'returns'])
                ->lockForUpdate()
                ->findOrFail((int) $validated['order_id']);

            if ($order->status !== Order::COMPLETED) {
                throw new Exception('Solo se pueden procesar devoluciones de órdenes completadas.');
            }

            if (!$order->client) {
                throw new Exception('No se encontró el cliente asociado a la orden.');
            }

            if ($order->order_date && $order->order_date->lt(Carbon::now()->subDays($windowDays))) {
                throw new Exception("La orden supera la ventana máxima de {$windowDays} días permitida para devoluciones.");
            }

            $refundMethod = $validated['refund_method'] ?? ReturnRefundMethodEnum::BALANCE->value;
            $restockInventory = array_key_exists('restock_inventory', $validated)
                ? (bool) $validated['restock_inventory']
                : true;
            $reason = $validated['reason'] ?? null;

            $canDirectApprove = in_array((int) (Auth::user()?->role_id ?? 0), [1, 2], true);
            $shouldApproveNow = !$requireApproval || (!empty($validated['approve_immediately']) && $canDirectApprove);

            $createdReturns = collect();

            foreach ($validated['items'] as $item) {
                $productId = (int) $item['product_id'];
                $qty = (int) $item['quantity'];

                if ($qty <= 0) {
                    throw new Exception('La cantidad a devolver debe ser mayor a cero.');
                }

                /** @var OrderDetail|null $detail */
                $detail = $order->details->firstWhere('product_id', $productId);
                if (!$detail) {
                    throw new Exception("El producto #{$productId} no pertenece a la orden #{$order->id}.");
                }

                $alreadyReturned = (int) ReturnEntry::where('order_id', $order->id)
                    ->where('product_id', $productId)
                    ->where(function ($q) {
                        $q->whereNull('status')->orWhere('status', ReturnEntry::APPROVED);
                    })
                    ->sum('quantity');

                $soldQty = (int) $detail->quantity;
                $availableQty = max(0, $soldQty - $alreadyReturned);

                if ($qty > $availableQty) {
                    $productName = $detail->product?->name ?? "Producto #{$productId}";
                    throw new Exception(
                        "No puede devolver {$qty} unidades de {$productName}. Disponible para devolver: {$availableQty}."
                    );
                }

                $unitPriceUsd = $this->resolveDetailUnitPriceUsd($order, $detail);
                $discountPct = (float) ($detail->discount_percentage ?? 0);
                $refundAmountUsd = round(($qty * $unitPriceUsd) * (100 - $discountPct) / 100, 2);

                $hasFiscalInvoice = !empty($order->fiscalHistory?->invoice_number);

                $returnEntry = ReturnEntry::create([
                    'order_id' => $order->id,
                    'generated_by_id' => Auth::id(),
                    'product_id' => $productId,
                    'quantity' => $qty,
                    'amount_refunded' => $refundAmountUsd,
                    'refund_method' => $refundMethod,
                    'restock_inventory' => $restockInventory,
                    'reason' => $reason,
                    'return_date' => Carbon::now(),
                    'status' => null,
                    'fiscal_status' => $hasFiscalInvoice
                        ? ReturnFiscalStatusEnum::PENDING->value
                        : ReturnFiscalStatusEnum::NOT_APPLICABLE->value,
                ]);

                $createdReturns->push($returnEntry);
            }

            if ($shouldApproveNow) {
                $updatedLots = count($createdReturns) === 1 ? ($validated['updated_lots'] ?? []) : [];
                $newLots = count($createdReturns) === 1 ? ($validated['new_lots'] ?? []) : [];

                foreach ($createdReturns as $entry) {
                    $this->processApprovalEffects($entry, $order, $updatedLots, $newLots, $restockInventory);
                }

                $this->dispatchFiscalCreditNoteIfApplicable($order, $createdReturns);
            }

            $createdReturns->each->refresh();

            return [
                'success' => true,
                'approved' => $shouldApproveNow,
                'fiscal_queued' => $shouldApproveNow && !empty($order->fiscalHistory?->invoice_number),
                'message' => $shouldApproveNow
                    ? 'Devolución procesada y aprobada exitosamente.'
                    : 'Devolución registrada. Pendiente de aprobación del supervisor.',
                'returns' => $createdReturns,
            ];
        });
    }

    /**
     * Aprobar una devolución pendiente (con o sin distribución manual de lotes).
     */
    public function approveReturn(
        ReturnEntry $returnEntry,
        array $updatedLots = [],
        array $newLots = [],
        ?bool $restockOverride = null
    ): ReturnEntry {
        if ($returnEntry->status !== null) {
            throw new Exception('Solo se puede aprobar una devolución que se encuentre pendiente.');
        }

        return DB::transaction(function () use ($returnEntry, $updatedLots, $newLots, $restockOverride) {
            $order = Order::with(['client', 'details.product', 'fiscalHistory'])
                ->lockForUpdate()
                ->findOrFail($returnEntry->order_id);

            $restock = $restockOverride ?? (bool) ($returnEntry->restock_inventory ?? true);

            $this->processApprovalEffects($returnEntry, $order, $updatedLots, $newLots, $restock);
            $this->dispatchFiscalCreditNoteIfApplicable($order, collect([$returnEntry]));

            return $returnEntry->fresh([
                'order.client',
                'order.seller',
                'order.fiscalHistory',
                'product.laboratory',
                'generatedBy',
                'approvedBy',
            ]);
        });
    }

    /**
     * Rechazar o actualizar el estado simple de una devolución.
     */
    public function updateStatus(ReturnEntry $returnEntry, string $status): ReturnEntry
    {
        if ($status === ReturnEntry::APPROVED) {
            return $this->approveReturn($returnEntry);
        }

        return DB::transaction(function () use ($returnEntry) {
            $returnEntry->update([
                'status' => ReturnEntry::REJECTED,
                'approved_by_id' => Auth::id(),
                'fiscal_status' => ReturnFiscalStatusEnum::NOT_APPLICABLE->value,
            ]);

            return $returnEntry->fresh();
        });
    }

    /**
     * Distribuir cantidad devuelta en lotes para devoluciones ya aprobadas sin lotes previos.
     */
    public function distributeLots(ReturnEntry $returnEntry, array $updatedLots, array $newLots): void
    {
        if ($returnEntry->status !== ReturnEntry::APPROVED) {
            throw new Exception('Solo se puede distribuir lotes en devoluciones aprobadas.');
        }

        DB::transaction(function () use ($returnEntry, $updatedLots, $newLots) {
            $this->applyLotDistribution($returnEntry, $updatedLots, $newLots);
        });
    }

    /**
     * Compatibilidad con el endpoint legado approve-with-distribution.
     */
    public function approveWithDistribution(ReturnEntry $returnEntry, array $updatedLots, array $newLots): ReturnEntry
    {
        return $this->approveReturn($returnEntry, $updatedLots, $newLots, true);
    }

    /**
     * Reintentar la emisión de Nota de Crédito fiscal sin duplicar inventario ni reembolso.
     */
    public function retryCreditNote(ReturnEntry $returnEntry): ReturnEntry
    {
        return DB::transaction(function () use ($returnEntry) {
            if ($returnEntry->status !== ReturnEntry::APPROVED) {
                throw new Exception('Solo se puede reintentar la Nota de Crédito en devoluciones aprobadas.');
            }

            $order = Order::with(['client', 'details.product', 'fiscalHistory'])->findOrFail($returnEntry->order_id);
            if (empty($order->fiscalHistory?->invoice_number)) {
                throw new Exception('La orden asociada no posee factura fiscal emitida.');
            }

            $fiscalStatus = $returnEntry->fiscal_status?->value ?? $returnEntry->fiscal_status;
            if ($fiscalStatus === ReturnFiscalStatusEnum::EMITTED->value) {
                throw new Exception('La Nota de Crédito de esta devolución ya fue emitida.');
            }

            $this->dispatchFiscalCreditNoteIfApplicable($order, collect([$returnEntry]));

            return $returnEntry->fresh(['order.fiscalHistory', 'fiscalCommand']);
        });
    }

    /**
     * Ejecutar efectos de aprobación: inventario (lotes, stock directo o merma) y reembolso financiero.
     */
    private function processApprovalEffects(
        ReturnEntry $returnEntry,
        Order $order,
        array $updatedLots,
        array $newLots,
        bool $restock
    ): void {
        $this->applyInventoryEffect($returnEntry, $order, $updatedLots, $newLots, $restock);
        $this->applyFinancialRefund($returnEntry, $order);

        $returnEntry->update([
            'status' => ReturnEntry::APPROVED,
            'approved_by_id' => Auth::id(),
            'restock_inventory' => $restock,
        ]);
    }

    /**
     * Aplicar efecto en inventario según configuración de lotes y destino (reingreso o merma).
     */
    private function applyInventoryEffect(
        ReturnEntry $returnEntry,
        Order $order,
        array $updatedLots,
        array $newLots,
        bool $restock
    ): void {
        $product = Product::lockForUpdate()->findOrFail($returnEntry->product_id);
        $stockBefore = (int) ($product->stock ?? 0);

        if (!$restock) {
            // Producto dañado/merma: no vuelve a stock vendible, queda trazabilidad de movimiento
            InventoryMovement::create([
                'product_id' => $product->id,
                'product_lot_id' => null,
                'movement_type' => 'return_waste',
                'quantity' => (int) $returnEntry->quantity,
                'invoice_id' => null,
                'supplier_id' => null,
                'order_id' => $order->id,
                'user_id' => Auth::id(),
                'stock_before' => $stockBefore,
                'stock_after' => $stockBefore,
                'movement_date' => now(),
            ]);
            return;
        }

        $enableLots = (bool) (GeneralSetting::first()?->enable_lots ?? true);
        $hasManualDistribution = !empty($updatedLots) || !empty($newLots);

        if ($enableLots && $hasManualDistribution) {
            $distributionForMovements = $this->applyLotDistribution($returnEntry, $updatedLots, $newLots);
            $stockAfter = (int) $product->fresh()->lots()->sum('quantity');

            Product::withoutEvents(function () use ($product, $stockAfter) {
                $product->update(['stock' => $stockAfter]);
            });

            foreach ($distributionForMovements as $item) {
                InventoryMovement::create([
                    'product_id' => $product->id,
                    'product_lot_id' => $item['product_lot_id'],
                    'movement_type' => 'return',
                    'quantity' => (int) $item['quantity'],
                    'invoice_id' => null,
                    'supplier_id' => null,
                    'order_id' => $order->id,
                    'user_id' => Auth::id(),
                    'stock_before' => $stockBefore,
                    'stock_after' => $stockAfter,
                    'movement_date' => now(),
                ]);
            }
            return;
        }

        if ($enableLots) {
            // Asignación automática al lote vendido en la orden o al lote más reciente del producto
            $detail = $order->details->firstWhere('product_id', $product->id);
            $targetLot = null;

            if ($detail && $detail->product_lot_id) {
                $targetLot = ProductLot::where('id', $detail->product_lot_id)
                    ->where('product_id', $product->id)
                    ->first();
            }

            if (!$targetLot) {
                $targetLot = ProductLot::where('product_id', $product->id)
                    ->orderByDesc('id')
                    ->first();
            }

            if ($targetLot) {
                ProductLot::withoutEvents(function () use ($targetLot, $returnEntry) {
                    $targetLot->increment('quantity', (int) $returnEntry->quantity);
                });
                $stockAfter = (int) $product->fresh()->lots()->sum('quantity');
                Product::withoutEvents(function () use ($product, $stockAfter) {
                    $product->update(['stock' => $stockAfter]);
                });

                InventoryMovement::create([
                    'product_id' => $product->id,
                    'product_lot_id' => $targetLot->id,
                    'movement_type' => 'return',
                    'quantity' => (int) $returnEntry->quantity,
                    'invoice_id' => null,
                    'supplier_id' => null,
                    'order_id' => $order->id,
                    'user_id' => Auth::id(),
                    'stock_before' => $stockBefore,
                    'stock_after' => $stockAfter,
                    'movement_date' => now(),
                ]);
                return;
            }
        }

        // Flujo directo sin lotes (Retail/Minimarket o producto sin lotes)
        $stockAfter = $stockBefore + (int) $returnEntry->quantity;
        Product::withoutEvents(function () use ($product, $stockAfter) {
            $product->update(['stock' => $stockAfter]);
        });

        InventoryMovement::create([
            'product_id' => $product->id,
            'product_lot_id' => null,
            'movement_type' => 'return',
            'quantity' => (int) $returnEntry->quantity,
            'invoice_id' => null,
            'supplier_id' => null,
            'order_id' => $order->id,
            'user_id' => Auth::id(),
            'stock_before' => $stockBefore,
            'stock_after' => $stockAfter,
            'movement_date' => now(),
        ]);
    }

    /**
     * Aplicar reembolso financiero: saldo a favor del cliente o egreso real de caja.
     */
    private function applyFinancialRefund(ReturnEntry $returnEntry, Order $order): void
    {
        $refundMethod = $returnEntry->refund_method?->value ?? $returnEntry->refund_method ?? ReturnRefundMethodEnum::BALANCE->value;
        $amountUsd = (float) $returnEntry->amount_refunded;

        if ($amountUsd <= 0) {
            return;
        }

        if ($refundMethod === ReturnRefundMethodEnum::BALANCE->value) {
            $order->client->increment('balance', $amountUsd);
            return;
        }

        // Registrar egreso real en transacciones financieras según la moneda de la orden
        $currency = strtoupper((string) ($order->currency ?? 'USD'));
        $usdConversion = (float) ($order->usd_conversion ?? 1);
        if ($usdConversion <= 0) {
            $usdConversion = 1;
        }

        $amountInCurrency = $currency === 'USD'
            ? $amountUsd
            : round($amountUsd * $usdConversion, 2);

        $txType = match ($refundMethod) {
            ReturnRefundMethodEnum::MOBILE_PAYMENT->value => TransactionType::MOBILE->value,
            ReturnRefundMethodEnum::TRANSFER->value => TransactionType::TRANSFER->value,
            default => TransactionType::CASH->value,
        };

        Transaction::create([
            'user_id' => Auth::id(),
            'category_id' => null,
            'exchange_rate' => $usdConversion,
            'description' => "Reembolso por devolución Orden #{$order->id} (Retorno #{$returnEntry->id})",
            'currency' => $currency,
            'type' => $txType,
            'amount' => $amountInCurrency,
            'movement_type' => 'OUT',
            'transaction_date' => now()->format('Y-m-d'),
        ]);
    }

    /**
     * Encolar Nota de Crédito fiscal automática cuando la orden tiene Factura Fiscal emitida.
     *
     * @param Collection<int, ReturnEntry> $returnEntries
     */
    private function dispatchFiscalCreditNoteIfApplicable(Order $order, Collection $returnEntries): void
    {
        $fiscalHistory = $order->fiscalHistory;
        if (!$fiscalHistory || empty($fiscalHistory->invoice_number)) {
            foreach ($returnEntries as $entry) {
                $entry->update(['fiscal_status' => ReturnFiscalStatusEnum::NOT_APPLICABLE->value]);
            }
            return;
        }

        $settings = GeneralSetting::first();
        $machineSerial = strtoupper(trim((string) ($fiscalHistory->fiscal_id ?: ($settings?->fiscal_printer_serial ?? ''))));
        if ($machineSerial === '') {
            $machineSerial = 'UNKNOWN';
        }

        // Calcular desglose exento y gravado en moneda fiscal (Bs)
        $exchangeRate = (float) ($fiscalHistory->exchange_rate ?: ($order->usd_conversion ?: 1));
        if ($exchangeRate <= 0) {
            $exchangeRate = 1;
        }

        $exemptAmount = 0.0;
        $taxableAmount = 0.0;

        foreach ($returnEntries as $entry) {
            $detail = $order->details->firstWhere('product_id', $entry->product_id);
            $isTaxable = (bool) ($detail?->product?->iva ?? false);
            $amountFiscal = round((float) $entry->amount_refunded * $exchangeRate, 2);

            if ($isTaxable) {
                $taxableAmount += $amountFiscal;
            } else {
                $exemptAmount += $amountFiscal;
            }
        }

        $exemptAmount = round($exemptAmount, 2);
        $taxableAmount = round($taxableAmount, 2);
        $totalRefundFiscal = round($exemptAmount + $taxableAmount, 2);

        if ($totalRefundFiscal <= 0) {
            return;
        }

        $invoiceDate = $fiscalHistory->invoice_date
            ? Carbon::parse($fiscalHistory->invoice_date)
            : ($order->order_date ?? now());

        $rawRif = (string) ($fiscalHistory->identification ?: ($order->client?->identification ?: 'V000000000'));
        $cleanRif = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $rawRif)) ?: 'V000000000';
        $clientName = trim((string) ($fiscalHistory->business_name ?: (($order->client?->name ?? '') . ' ' . ($order->client?->last_name ?? '')))) ?: 'CLIENTE CONTADO';

        $command = FiscalCommand::create([
            'command' => 'CREDIT_NOTE',
            'status' => 'pending',
            'payload' => [
                'order_id' => $order->id,
                'return_entry_ids' => $returnEntries->pluck('id')->values()->all(),
                'invoice_number' => (string) $fiscalHistory->invoice_number,
                'machine_serial' => $machineSerial,
                'invoice_date' => $invoiceDate->format('Y-m-d'),
                'invoice_hour' => $invoiceDate->format('H:i:s'),
                'refund_amount' => $totalRefundFiscal,
                'exempt_amount' => $exemptAmount,
                'taxable_amount' => $taxableAmount,
                'is_taxable' => $taxableAmount > 0,
                'client_name' => mb_substr($clientName, 0, 38),
                'client_rif' => mb_substr($cleanRif, 0, 12),
                'description' => "DEVOLUCION ORDEN #{$order->id}",
            ],
        ]);

        foreach ($returnEntries as $entry) {
            $entry->update([
                'fiscal_command_id' => $command->id,
                'fiscal_status' => ReturnFiscalStatusEnum::PENDING->value,
            ]);
        }
    }

    /**
     * Resolver el precio unitario en USD desde el detalle real de la orden en BD.
     */
    private function resolveDetailUnitPriceUsd(Order $order, OrderDetail $detail): float
    {
        $priceUsd = (float) ($detail->unit_price_usd ?? 0);
        if ($priceUsd > 0) {
            return $priceUsd;
        }

        $price = (float) ($detail->price ?? 0);
        $currency = strtoupper((string) ($order->currency ?? 'USD'));
        $usdConversion = (float) ($order->usd_conversion ?? 1);
        if ($usdConversion <= 0) {
            $usdConversion = 1;
        }

        return $currency === 'USD' ? $price : ($price / $usdConversion);
    }

    /**
     * Aplicar distribución manual en lotes existentes o nuevos.
     */
    private function applyLotDistribution(ReturnEntry $returnEntry, array $updatedLots, array $newLots): array
    {
        $productId = $returnEntry->product_id;
        $returnQty = (int) $returnEntry->quantity;

        $totalDistributed = 0;
        $distributionForMovements = [];

        ProductLot::withoutEvents(function () use ($updatedLots, $newLots, $productId, &$totalDistributed, &$distributionForMovements) {
            foreach ($updatedLots as $row) {
                $lot = ProductLot::where('id', $row['id'])->where('product_id', $productId)->first();
                if (!$lot) {
                    throw new Exception('Lote no encontrado o no pertenece al producto.');
                }
                $newQty = (int) ($row['quantity'] ?? 0);
                $delta = $newQty - (int) $lot->quantity;
                if ($delta > 0) {
                    $lot->increment('quantity', $delta);
                    $totalDistributed += $delta;
                    $distributionForMovements[] = ['product_lot_id' => $lot->id, 'quantity' => $delta];
                }
            }

            foreach ($newLots as $row) {
                $qty = (int) ($row['quantity'] ?? 0);
                if ($qty <= 0) {
                    continue;
                }
                $newLot = ProductLot::create([
                    'product_id' => $productId,
                    'lot_number' => $row['lot_number'] ?? '',
                    'expiration_date' => !empty($row['expiration_date']) ? $row['expiration_date'] : null,
                    'location' => $row['location'] ?? '',
                    'quantity' => $qty,
                ]);
                $totalDistributed += $qty;
                $distributionForMovements[] = ['product_lot_id' => $newLot->id, 'quantity' => $qty];
            }
        });

        if ($totalDistributed !== $returnQty) {
            throw new Exception("La cantidad distribuida ({$totalDistributed}) debe coincidir con las unidades devueltas ({$returnQty}).");
        }

        return $distributionForMovements;
    }
}
