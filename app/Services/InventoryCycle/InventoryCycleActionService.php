<?php

declare(strict_types=1);

namespace App\Services\InventoryCycle;

use App\Models\InventoryCycle;
use App\Models\InventoryMovement;
use App\Models\InvoiceCount;
use App\Models\InvoiceCountDistribution;
use App\Models\Product;
use App\Models\ProductCount;
use App\Models\ProductDistribution;
use App\Models\ProductLot;
use App\Models\SaleCount;
use App\Models\SaleCountDistribution;
use App\Models\UserCyclicQuota;
use App\Observers\ProductLotObserver;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class InventoryCycleActionService
{
    public function createProductCount(int $productId, array $data): array
    {
        return DB::transaction(function () use ($productId, $data) {
            try {
                $product = Product::findOrFail($productId);

                $activeCycle = $this->getActiveCycle();
                if (!$activeCycle) {
                    return [
                        'success' => false,
                        'message' => 'No existe un ciclo de inventario activo. Contacte al administrador.',
                        'data' => null
                    ];
                }

                $systemStock = (float) $data['system_quantity'];
                $rawBarcode = $product->barcode ? trim((string) $product->barcode) : '';
                $rawId = trim((string) $product->id);
                $hasRealBarcode = !empty($rawBarcode) && $rawBarcode !== $rawId;
                $allowWithoutBarcode = (bool) ($data['allow_without_barcode'] ?? false);

                // Si el producto tiene código de barras real Y tiene stock > 0, el escaneo es obligatorio
                // Si el stock en sistema es 0 o menor, se permite el ingreso manual
                $requiresStrictBarcode = $hasRealBarcode && $systemStock > 0;

                if ($requiresStrictBarcode) {
                    $scannedBarcode = isset($data['barcode']) ? trim((string) $data['barcode']) : '';
                    if (empty($scannedBarcode) || $scannedBarcode !== $rawBarcode) {
                        return [
                            'success' => false,
                            'message' => 'El código de barras no coincide con el producto seleccionado.',
                            'data' => null
                        ];
                    }
                } elseif (!$allowWithoutBarcode && !empty($rawBarcode)) {
                    $scannedBarcode = isset($data['barcode']) ? trim((string) $data['barcode']) : '';
                    if (!empty($scannedBarcode) && $scannedBarcode !== $rawBarcode) {
                        return [
                            'success' => false,
                            'message' => 'El código de barras no coincide con el producto seleccionado.',
                            'data' => null
                        ];
                    }
                }

                $expectedDiscrepancy = $data['counted_quantity'] - $systemStock;
                if ((int) $expectedDiscrepancy !== (int) $data['discrepancy']) {
                    Log::warning('Discrepancia no coincide con el cálculo del backend', [
                        'product_id' => $productId,
                        'frontend_system_stock' => $systemStock,
                        'counted_quantity' => $data['counted_quantity'],
                        'frontend_discrepancy' => $data['discrepancy'],
                        'backend_discrepancy' => $expectedDiscrepancy
                    ]);

                    $data['discrepancy'] = $expectedDiscrepancy;
                }

                // Si no hay discrepancia y no es simple, aprobar automáticamente sin supervisor
                $isSimple = \App\Models\GeneralSetting::first()?->cyclic_inventory_mode === 'simple';
                $finalDiscrepancy = $data['discrepancy'];
                $status = ($finalDiscrepancy == 0 && !$isSimple) ? 'approved' : 'pending';
                $userId = Auth::id();
                $today = now()->toDateString();
                $activeQuota = UserCyclicQuota::where('user_id', $userId)
                    ->where('cycle_id', $activeCycle->id)
                    ->where('quota_date', $today)
                    ->orderBy('quota_tier', 'desc')
                    ->first();

                $quotaTier = $activeQuota ? $activeQuota->quota_tier : 1;
                $pointsEarned = match (true) {
                    $quotaTier >= 3 => 4,
                    $quotaTier === 2 => 2,
                    default => 1,
                };
                $supervisorId = null;

                $productCount = ProductCount::create([
                    'product_id' => $product->id,
                    'user_id' => $userId,
                    'cycle_id' => $activeCycle->id,
                    'barcode_scanned' => $allowWithoutBarcode ? null : ($data['barcode'] ?? null),
                    'system_quantity' => $systemStock,
                    'counted_quantity' => $data['counted_quantity'],
                    'discrepancy' => $finalDiscrepancy,
                    'status' => $status,
                    'supervisor_id' => $supervisorId,
                    'product_lot_id' => null,
                    'count_date' => now(),
                    'quota_tier' => $quotaTier,
                    'points_earned' => $pointsEarned,
                ]);

                $productCount->load(['product', 'user', 'cycle']);

                if ($status === 'approved') {
                    $this->createVerificationMovement($product, $systemStock, $productCount->created_at, $productCount->id);
                }

                $message = $status === 'approved' 
                    ? "Conteo registrado y aprobado automáticamente (sin discrepancia)."
                    : "Conteo registrado exitosamente.";

                return [
                    'success' => true,
                    'message' => $message,
                    'data' => $productCount
                ];

            } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
                return [
                    'success' => false,
                    'message' => 'Producto no encontrado.',
                    'data' => null
                ];
            } catch (\Exception $e) {
                Log::error('Error al registrar conteo de inventario', [
                    'product_id' => $productId,
                    'user_id' => Auth::id(),
                    'data' => $data,
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString()
                ]);

                return [
                    'success' => false,
                    'message' => 'Error interno del servidor al registrar el conteo: ' . $e->getMessage(),
                    'data' => null
                ];
            }
        });
    }

    public function getActiveCycle(): ?InventoryCycle
    {
        return InventoryCycle::where('status', 'active')->first();
    }

    public function getActiveCycleInfo(): ?InventoryCycle
    {
        return $this->getActiveCycle();
    }

    /**
     * Crea un movimiento de inventario tipo verificación cuando el conteo físico coincide con el stock en sistema.
     */
    private function createVerificationMovement(Product $product, float|int $stockQuantity, \DateTimeInterface $movementDate, ?int $productCountId = null): void
    {
        InventoryMovement::create([
            'product_id' => $product->id,
            'product_lot_id' => null,
            'movement_type' => 'verification',
            'quantity' => 0,
            'user_id' => Auth::id(),
            'product_count_id' => $productCountId,
            'stock_before' => $stockQuantity,
            'stock_after' => $stockQuantity,
            'movement_date' => $movementDate,
        ]);
    }

    public function calculateCurrentStock(Product $product): int
    {
        if (!$product->lots || $product->lots->isEmpty()) {
            return 0;
        }

        return (int) $product->lots
            ->filter(function ($lot) {
                return $lot->quantity > 0;
            })
            ->sum('quantity');
    }

    public function processAction(ProductCount $productCount, string $action, array $data = []): array
    {
        if ($productCount->status !== 'pending') {
            return [
                'success' => false,
                'message' => "Este conteo ya fue procesado. Estado actual: {$productCount->status}",
                'data' => null
            ];
        }

        return DB::transaction(function () use ($productCount, $action, $data) {
            try {
                if ($action === 'approve' || ($action === 'reject' && isset($data['corrected_quantity']))) {
                    return $this->approveOrCorrectCount($productCount, $data);
                } elseif ($action === 'reject') {
                    return $this->rejectCount($productCount);
                }

                return ['success' => false, 'message' => 'Acción no válida.', 'data' => null];

            } catch (\Exception $e) {
                Log::error('Error procesando acción de conteo', [
                    'product_count_id' => $productCount->id,
                    'action' => $action,
                    'data' => $data,
                    'error' => $e->getMessage()
                ]);

                return ['success' => false, 'message' => 'Error al procesar la acción: ' . $e->getMessage(), 'data' => null];
            }
        });
    }

    private function approveOrCorrectCount(ProductCount $productCount, array $data): array
    {
        $isCorrection = isset($data['corrected_quantity']);
        $finalQuantity = $isCorrection ? (float) $data['corrected_quantity'] : (float) $productCount->counted_quantity;

        $product = $productCount->product;
        
        // Stock actual real antes de cualquier modificación
        $stockBefore = (float) $product->lots()->where('quantity', '>', 0)->sum('quantity');
        $finalDiscrepancy = $finalQuantity - $stockBefore;
        
        // Sincronizar system_quantity con el stock al momento de la verificación
        $productCount->system_quantity = $stockBefore;

        $distributionsCreated = false;

        // Desactivar temporalmente el observer para evitar que genere movimientos duplicados
        ProductLotObserver::$skipMovementCreation = true;
        try {
            $enableLots = \App\Models\GeneralSetting::first()?->enable_lots ?? true;
            if (!$enableLots) {
                $uniqueLot = ProductLot::where('product_id', $product->id)
                    ->where('lot_number', 'LOTE-UNICO')
                    ->first();

                if (!$uniqueLot) {
                    $uniqueLot = ProductLot::create([
                        'product_id'      => $product->id,
                        'lot_number'      => 'LOTE-UNICO',
                        'expiration_date' => '2050-12-31',
                        'quantity'        => $finalQuantity,
                        'unit_cost'       => $product->unit_cost ?? 0,
                        'location'        => 'PRINCIPAL',
                        'supplier_id'     => null,
                    ]);
                } else {
                    $uniqueLot->update(['quantity' => $finalQuantity]);
                }

                ProductDistribution::create([
                    'product_count_id' => $productCount->id,
                    'product_lot_id'   => $uniqueLot->id,
                    'quantity'         => $finalQuantity,
                ]);
                $distributionsCreated = true;
            }

            if (!empty($data['updated_lots'])) {
                foreach ($data['updated_lots'] as $lotData) {
                    $lotToUpdate = ProductLot::find($lotData['id']);

                    if ($lotToUpdate && $lotToUpdate->product_id === $product->id) {
                        $updateData = ['quantity' => $lotData['quantity']];
                        
                        if (isset($lotData['lot_number'])) {
                            $updateData['lot_number'] = $lotData['lot_number'];
                        }
                        if (isset($lotData['expiration_date'])) {
                            $updateData['expiration_date'] = $lotData['expiration_date'];
                        }
                        if (isset($lotData['location'])) {
                            $updateData['location'] = $lotData['location'];
                        }
                        
                        $lotToUpdate->update($updateData);

                        ProductDistribution::create([
                            'product_count_id' => $productCount->id,
                            'product_lot_id'   => $lotToUpdate->id,
                            'quantity'         => $lotData['quantity'],
                        ]);
                        $distributionsCreated = true;
                    } else {
                        Log::warning('Se intentó actualizar un lote no encontrado o no perteneciente al producto.', [
                            'lot_data'   => $lotData,
                            'product_id' => $product->id,
                        ]);
                    }
                }
            }

            if (!empty($data['new_lots'])) {
                $existingLotsWithCost = ProductLot::where('product_id', $product->id)
                    ->where('unit_cost', '>', 0)
                    ->get();

                $newLotUnitCost = $existingLotsWithCost->isNotEmpty()
                    ? round($existingLotsWithCost->avg('unit_cost'), 2)
                    : ($product->unit_price ?? 0);

                foreach ($data['new_lots'] as $lotData) {
                    $newLot = ProductLot::create([
                        'product_id'      => $product->id,
                        'lot_number'      => $lotData['lot_number'],
                        'expiration_date' => $lotData['expiration_date'],
                        'quantity'        => $lotData['quantity'],
                        'unit_cost'       => $newLotUnitCost,
                        'supplier_id'     => null,
                    ]);

                    ProductDistribution::create([
                        'product_count_id' => $productCount->id,
                        'product_lot_id'   => $newLot->id,
                        'quantity'         => $newLot->quantity,
                    ]);
                    $distributionsCreated = true;
                }
            }

            // Si se crearon distribuciones manuales (por lotes actualizados o nuevos):
            if ($distributionsCreated) {
                $stockAfter = (float) $product->lots()->where('quantity', '>', 0)->sum('quantity');
                $product->updateQuietly(['stock' => $stockAfter]);
                $diff = round($stockAfter - $stockBefore, 4);

                if (abs($diff) > 0.0001) {
                    $targetLot = $product->lots()->where('quantity', '>', 0)->first();
                    InventoryMovement::create([
                        'product_id'       => $product->id,
                        'product_lot_id'   => $targetLot?->id,
                        'movement_type'    => $diff > 0 ? 'adjustment' : 'loss',
                        'quantity'         => $diff,
                        'invoice_id'       => null,
                        'supplier_id'      => null,
                        'order_id'         => null,
                        'user_id'          => Auth::id(),
                        'product_count_id' => $productCount->id,
                        'stock_before'     => $stockBefore,
                        'stock_after'      => $stockAfter,
                        'movement_date'    => now(),
                    ]);
                } else {
                    $this->createVerificationMovement($product, (int) $stockAfter, now(), $productCount->id);
                }
            } else {
                // Si no se creó ninguna distribución manual:
                if (abs($finalDiscrepancy) < 0.0001) {
                    $stockAfter = $stockBefore;
                    $finalQuantity = (float) $stockBefore;
                    $product->updateQuietly(['stock' => $stockAfter]);
                    $this->createVerificationMovement($product, (int) $stockAfter, now(), $productCount->id);
                } else {
                    $targetLot = $product->lots()->where('quantity', '>', 0)->orderBy('id', 'desc')->first()
                        ?? $product->lots()->orderBy('id', 'desc')->first();

                    if ($targetLot) {
                        $targetLot->quantity = max(0, $targetLot->quantity + $finalDiscrepancy);
                        $targetLot->saveQuietly();

                        ProductDistribution::create([
                            'product_count_id' => $productCount->id,
                            'product_lot_id'   => $targetLot->id,
                            'quantity'         => $targetLot->quantity,
                        ]);
                    } else {
                        $targetLot = ProductLot::create([
                            'product_id'      => $product->id,
                            'lot_number'      => 'LOTE-AJUSTE',
                            'expiration_date' => now()->addYears(2)->toDateString(),
                            'quantity'        => max(0, $finalQuantity),
                            'unit_cost'       => $product->unit_cost ?? 0,
                        ]);

                        ProductDistribution::create([
                            'product_count_id' => $productCount->id,
                            'product_lot_id'   => $targetLot->id,
                            'quantity'         => $targetLot->quantity,
                        ]);
                    }

                    $stockAfter = (float) $product->lots()->where('quantity', '>', 0)->sum('quantity');
                    $product->updateQuietly(['stock' => $stockAfter]);
                    $diff = round($stockAfter - $stockBefore, 4);

                    InventoryMovement::create([
                        'product_id'       => $product->id,
                        'product_lot_id'   => $targetLot->id,
                        'movement_type'    => $diff > 0 ? 'adjustment' : 'loss',
                        'quantity'         => $diff,
                        'invoice_id'       => null,
                        'supplier_id'      => null,
                        'order_id'         => null,
                        'user_id'          => Auth::id(),
                        'product_count_id' => $productCount->id,
                        'stock_before'     => $stockBefore,
                        'stock_after'      => $stockAfter,
                        'movement_date'    => now(),
                    ]);
                }
            }
        } finally {
            ProductLotObserver::$skipMovementCreation = false;
        }

        // Evaluación de penalizaciones para el operador que reportó el conteo inicial
        $originalCounted = (float) $productCount->getOriginal('counted_quantity');
        $correctionDiff = $isCorrection ? abs($originalCounted - $finalQuantity) : 0;
        
        $errorPenaltyType = null;
        $penaltyPoints = 0;

        // Si el operador original reportó discrepancia pero en la verificación física el stock del sistema estaba exacto: Falsa Discrepancia (-20 pts)
        if ($finalDiscrepancy == 0 && abs($originalCounted - $stockBefore) > 0.0001) {
            $errorPenaltyType = 'false_discrepancy';
            $penaltyPoints = 20;
        } elseif ($isCorrection && $correctionDiff > 0.0001) {
            // Si la discrepancia reportada por el operador fue incorrecta/variable a la real: Discrepancia Errónea (-10 pts)
            $errorPenaltyType = 'wrong_discrepancy';
            $penaltyPoints = 10;
        }

        $productCount->counted_quantity      = $finalQuantity;
        $productCount->discrepancy           = $finalDiscrepancy;
        $productCount->status                = 'approved';
        $productCount->supervisor_id         = Auth::id();
        $productCount->correction_difference = $correctionDiff;
        $productCount->error_penalty_type    = $errorPenaltyType;
        $productCount->penalty_points        = $penaltyPoints;
        $productCount->save();

        $productCount->load(['product', 'user', 'distributions.productLot']);

        $message = $finalDiscrepancy == 0
            ? "Conteo verificado: lo contado coincide con el sistema para '{$productCount->product->name}'. No se realizó ajuste."
            : "Ajuste de inventario aplicado exitosamente a '{$productCount->product->name}'.";

        return [
            'success' => true,
            'message' => $message,
            'data'    => $productCount
        ];
    }

    private function rejectCount(ProductCount $productCount): array
    {
        $productCount->update([
            'status' => 'rejected',
            'supervisor_id' => Auth::id()
        ]);

        $productCount->load(['product', 'user']);

        return [
            'success' => true,
            'message' => "Conteo rechazado. No se realizaron cambios en el inventario de '{$productCount->product->name}'.",
            'data' => $productCount
        ];
    }

    public function createInvoiceCount(int $productId, array $data): array
    {
        return DB::transaction(function () use ($productId, $data) {
            try {
                $product = Product::findOrFail($productId);
                $activeCycle = $this->getActiveCycle();

                if (!$activeCycle) {
                    return ['success' => false, 'message' => 'No existe un ciclo activo.', 'data' => null];
                }

                $allowWithoutBarcode = $data['allow_without_barcode'] ?? false;
                if (!$allowWithoutBarcode) {
                    if ($product->barcode && isset($data['barcode']) && $product->barcode !== $data['barcode']) {
                        return ['success' => false, 'message' => 'El código de barras no coincide.', 'data' => null];
                    }
                }

                $expectedDiscrepancy = $data['counted_quantity'] - $data['system_quantity'];
                if ((int) $expectedDiscrepancy !== (int) $data['discrepancy']) {
                    $data['discrepancy'] = $expectedDiscrepancy;
                }

                $finalDiscrepancy = $data['discrepancy'];
                $status = ($finalDiscrepancy == 0) ? 'approved' : 'pending';
                $supervisorId = null;

                $invoiceCount = InvoiceCount::create([
                    'product_id'       => $product->id,
                    'user_id'          => Auth::id(),
                    'cycle_id'         => $activeCycle->id,
                    'system_quantity'  => $data['system_quantity'],
                    'counted_quantity' => $data['counted_quantity'],
                    'discrepancy'      => $finalDiscrepancy,
                    'status'           => $status,
                    'supervisor_id'    => $supervisorId,
                    'invoice_id'       => $data['invoice_id'] ?? null,
                    'points_earned'    => 2,
                ]);

                $invoiceCount->load(['product', 'user', 'cycle']);

                if ($status === 'approved') {
                    $this->createVerificationMovement($product, $data['system_quantity'], $invoiceCount->created_at);
                }

                $message = $status === 'approved' 
                    ? "Conteo de factura verificado y aprobado automáticamente."
                    : "Conteo de factura registrado.";

                return ['success' => true, 'message' => $message, 'data' => $invoiceCount];

            } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
                return ['success' => false, 'message' => 'Producto no encontrado.', 'data' => null];
            } catch (\Exception $e) {
                Log::error('Error al registrar conteo de factura', ['error' => $e->getMessage()]);
                return ['success' => false, 'message' => 'Error interno: ' . $e->getMessage(), 'data' => null];
            }
        });
    }

    public function processInvoiceCountAction(InvoiceCount $invoiceCount, string $action, array $data = []): array
    {
        if ($invoiceCount->status !== 'pending') {
            return ['success' => false, 'message' => "Este conteo de factura ya fue procesado. Estado actual: {$invoiceCount->status}", 'data' => null];
        }

        return DB::transaction(function () use ($invoiceCount, $action, $data) {
            try {
                if ($action === 'approve' || ($action === 'reject' && isset($data['corrected_quantity']))) {
                    return $this->approveOrCorrectInvoiceCount($invoiceCount, $data);
                } elseif ($action === 'reject') {
                    return $this->rejectInvoiceCount($invoiceCount);
                }
                return ['success' => false, 'message' => 'Acción no válida.', 'data' => null];
            } catch (\Exception $e) {
                Log::error('Error procesando acción de conteo de factura', ['invoice_count_id' => $invoiceCount->id, 'error' => $e->getMessage()]);
                return ['success' => false, 'message' => 'Error al procesar la acción: ' . $e->getMessage(), 'data' => null];
            }
        });
    }

    private function approveOrCorrectInvoiceCount(InvoiceCount $invoiceCount, array $data): array
    {
        $isCorrection = isset($data['corrected_quantity']);
        $finalQuantity = $isCorrection ? (float) $data['corrected_quantity'] : (float) $invoiceCount->counted_quantity;
        
        $product = $invoiceCount->product;

        // Stock actual real antes de cualquier modificación
        $stockBefore = (float) $product->lots()->where('quantity', '>', 0)->sum('quantity');
        $finalDiscrepancy = $finalQuantity - $stockBefore;
        
        // Sincronizar system_quantity con el stock al momento de la verificación
        $invoiceCount->system_quantity = $stockBefore;

        $distributionsCreated = false;

        ProductLotObserver::$skipMovementCreation = true;
        try {
            if (!empty($data['updated_lots'])) {
                foreach ($data['updated_lots'] as $lotData) {
                    $lotToUpdate = ProductLot::find($lotData['id']);
                    if ($lotToUpdate && $lotToUpdate->product_id === $product->id) {
                        $updateData = ['quantity' => $lotData['quantity']];
                        if (isset($lotData['lot_number'])) $updateData['lot_number'] = $lotData['lot_number'];
                        if (isset($lotData['expiration_date'])) $updateData['expiration_date'] = $lotData['expiration_date'];
                        if (isset($lotData['location'])) $updateData['location'] = $lotData['location'];
                        $lotToUpdate->update($updateData);
                        InvoiceCountDistribution::create([
                            'invoice_count_id' => $invoiceCount->id,
                            'product_lot_id'   => $lotToUpdate->id,
                            'quantity'         => $lotData['quantity'],
                        ]);
                        $distributionsCreated = true;
                    }
                }
            }

            if (!empty($data['new_lots'])) {
                $existingLotsWithCost = ProductLot::where('product_id', $product->id)->where('unit_cost', '>', 0)->get();
                $newLotUnitCost = $existingLotsWithCost->isNotEmpty() ? round($existingLotsWithCost->avg('unit_cost'), 2) : ($product->unit_price ?? 0);

                foreach ($data['new_lots'] as $lotData) {
                    $newLot = ProductLot::create([
                        'product_id'      => $product->id,
                        'lot_number'      => $lotData['lot_number'],
                        'expiration_date' => $lotData['expiration_date'],
                        'location'        => $lotData['location'] ?? null,
                        'quantity'        => $lotData['quantity'],
                        'unit_cost'       => $newLotUnitCost,
                        'supplier_id'     => null,
                    ]);
                    InvoiceCountDistribution::create([
                        'invoice_count_id' => $invoiceCount->id,
                        'product_lot_id'   => $newLot->id,
                        'quantity'         => $newLot->quantity,
                    ]);
                    $distributionsCreated = true;
                }
            }

            // Si se crearon distribuciones manuales (por lotes actualizados o nuevos):
            if ($distributionsCreated) {
                $stockAfter = (float) $product->lots()->where('quantity', '>', 0)->sum('quantity');
                $product->updateQuietly(['stock' => $stockAfter]);
                $diff = round($stockAfter - $stockBefore, 4);

                if (abs($diff) > 0.0001) {
                    $targetLot = $product->lots()->where('quantity', '>', 0)->first();
                    InventoryMovement::create([
                        'product_id'       => $product->id,
                        'product_lot_id'   => $targetLot?->id,
                        'movement_type'    => $diff > 0 ? 'adjustment' : 'loss',
                        'quantity'         => $diff,
                        'invoice_id'       => null,
                        'supplier_id'      => null,
                        'order_id'         => null,
                        'user_id'          => Auth::id(),
                        'product_count_id' => null,
                        'stock_before'     => $stockBefore,
                        'stock_after'      => $stockAfter,
                        'movement_date'    => now(),
                    ]);
                } else {
                    $this->createVerificationMovement($product, (int) $stockAfter, now());
                }
            } else {
                // Si no se creó ninguna distribución manual:
                if (abs($finalDiscrepancy) < 0.0001) {
                    $stockAfter = $stockBefore;
                    $finalQuantity = (float) $stockBefore;
                    $product->updateQuietly(['stock' => $stockAfter]);
                    $this->createVerificationMovement($product, (int) $stockAfter, now());
                } else {
                    $targetLot = $product->lots()->where('quantity', '>', 0)->orderBy('id', 'desc')->first()
                        ?? $product->lots()->orderBy('id', 'desc')->first();

                    if ($targetLot) {
                        $targetLot->quantity = max(0, $targetLot->quantity + $finalDiscrepancy);
                        $targetLot->saveQuietly();

                        InvoiceCountDistribution::create([
                            'invoice_count_id' => $invoiceCount->id,
                            'product_lot_id'   => $targetLot->id,
                            'quantity'         => $targetLot->quantity,
                        ]);
                    } else {
                        $targetLot = ProductLot::create([
                            'product_id'      => $product->id,
                            'lot_number'      => 'LOTE-AJUSTE',
                            'expiration_date' => now()->addYears(2)->toDateString(),
                            'quantity'        => max(0, $finalQuantity),
                            'unit_cost'       => $product->unit_cost ?? 0,
                        ]);

                        InvoiceCountDistribution::create([
                            'invoice_count_id' => $invoiceCount->id,
                            'product_lot_id'   => $targetLot->id,
                            'quantity'         => $targetLot->quantity,
                        ]);
                    }

                    $stockAfter = (float) $product->lots()->where('quantity', '>', 0)->sum('quantity');
                    $product->updateQuietly(['stock' => $stockAfter]);
                    $diff = round($stockAfter - $stockBefore, 4);

                    InventoryMovement::create([
                        'product_id'       => $product->id,
                        'product_lot_id'   => $targetLot->id,
                        'movement_type'    => $diff > 0 ? 'adjustment' : 'loss',
                        'quantity'         => $diff,
                        'invoice_id'       => null,
                        'supplier_id'      => null,
                        'order_id'         => null,
                        'user_id'          => Auth::id(),
                        'product_count_id' => null,
                        'stock_before'     => $stockBefore,
                        'stock_after'      => $stockAfter,
                        'movement_date'    => now(),
                    ]);
                }
            }
        } finally {
            ProductLotObserver::$skipMovementCreation = false;
        }

        $originalCounted = (float) $invoiceCount->getOriginal('counted_quantity');
        $correctionDiff = $isCorrection ? abs($originalCounted - $finalQuantity) : 0;
        
        $errorPenaltyType = null;
        $penaltyPoints = 0;

        if ($finalDiscrepancy == 0 && abs($originalCounted - $stockBefore) > 0.0001) {
            $errorPenaltyType = 'false_discrepancy';
            $penaltyPoints = 20;
        } elseif ($isCorrection && $correctionDiff > 0.0001) {
            $errorPenaltyType = 'wrong_discrepancy';
            $penaltyPoints = 10;
        }

        $invoiceCount->counted_quantity      = $finalQuantity;
        $invoiceCount->discrepancy           = $finalDiscrepancy;
        $invoiceCount->status                = 'approved';
        $invoiceCount->supervisor_id         = Auth::id();
        $invoiceCount->correction_difference = $correctionDiff;
        $invoiceCount->error_penalty_type    = $errorPenaltyType;
        $invoiceCount->penalty_points        = $penaltyPoints;
        $invoiceCount->save();

        $invoiceCount->load(['product', 'user', 'distributions.productLot']);
        $message = $finalDiscrepancy == 0
            ? "Conteo de factura verificado: coincide con sistema para '{$product->name}'. No se realizó ajuste."
            : "Ajuste de factura para '{$product->name}' aplicado.";
        return ['success' => true, 'message' => $message, 'data' => $invoiceCount];
    }

    private function rejectInvoiceCount(InvoiceCount $invoiceCount): array
    {
        $invoiceCount->update(['status' => 'rejected', 'supervisor_id' => Auth::id()]);
        $invoiceCount->load(['product', 'user']);
        return ['success' => true, 'message' => "Conteo de factura para '{$invoiceCount->product->name}' rechazado.", 'data' => $invoiceCount];
    }

    public function closeActiveCycle(bool $rejectPending = false): array
    {
        return DB::transaction(function () use ($rejectPending) {
            $activeCycle = $this->getActiveCycle();

            if (!$activeCycle) {
                return ['success' => false, 'message' => 'No se encontró ningún ciclo de inventario activo para cerrar.'];
            }

            if ($rejectPending) {
                ProductCount::where('cycle_id', $activeCycle->id)
                    ->where('status', 'pending')
                    ->update(['status' => 'rejected']);

                InvoiceCount::where('cycle_id', $activeCycle->id)
                    ->where('status', 'pending')
                    ->update(['status' => 'rejected']);

                SaleCount::where('cycle_id', $activeCycle->id)
                    ->where('status', 'pending')
                    ->update(['status' => 'rejected']);
            } else {
                $hasPendingProductCounts = ProductCount::where('cycle_id', $activeCycle->id)
                    ->where('status', 'pending')
                    ->exists();

                $hasPendingInvoiceCounts = InvoiceCount::where('cycle_id', $activeCycle->id)
                    ->where('status', 'pending')
                    ->exists();

                $hasPendingSaleCounts = SaleCount::where('cycle_id', $activeCycle->id)
                    ->where('status', 'pending')
                    ->exists();

                if ($hasPendingProductCounts || $hasPendingInvoiceCounts || $hasPendingSaleCounts) {
                    return [
                        'success' => false,
                        'message' => 'No se puede cerrar el ciclo. Aún existen conteos pendientes de aprobación o rechazo.'
                    ];
                }
            }

            $activeCycle->status = 'closed';
            $activeCycle->end_date = now();
            $activeCycle->save();

            return ['success' => true, 'message' => 'El ciclo de inventario ha sido cerrado exitosamente.'];
        });
    }

    public function createNewCycle(): array
    {
        if ($this->hasActiveCycle()) {
            return ['success' => false, 'message' => 'Ya existe un ciclo de inventario activo.'];
        }

        InventoryCycle::create([
            'start_date' => now(),
            'end_date' => null,
            'status' => 'active',
            'created_by_id' => Auth::id(),
        ]);

        return ['success' => true, 'message' => 'Nuevo ciclo de inventario creado y activado.'];
    }

    public function hasActiveCycle(): bool
    {
        return InventoryCycle::where('status', 'active')->exists();
    }

    public function createSaleCount(int $productId, array $data): array
    {
        return DB::transaction(function () use ($productId, $data) {
            try {
                $product = Product::findOrFail($productId);
                $activeCycle = $this->getActiveCycle();

                if (!$activeCycle) {
                    return ['success' => false, 'message' => 'No existe un ciclo de inventario activo.', 'data' => null];
                }

                $allowWithoutBarcode = $data['allow_without_barcode'] ?? false;

                // Solo validar código de barras si no se permite sin código de barras
                if (!$allowWithoutBarcode) {
                    if ($product->barcode && isset($data['barcode']) && $product->barcode !== $data['barcode']) {
                        return ['success' => false, 'message' => 'El código de barras no coincide con el producto.', 'data' => null];
                    }
                }

                $expectedDiscrepancy = $data['counted_quantity'] - $data['system_quantity'];
                if ((int) $expectedDiscrepancy !== (int) $data['discrepancy']) {
                    $data['discrepancy'] = $expectedDiscrepancy;
                }

                // Si no hay discrepancia, aprobar automáticamente sin supervisor
                $finalDiscrepancy = $data['discrepancy'];
                $status = ($finalDiscrepancy == 0) ? 'approved' : 'pending';
                $supervisorId = null; // No hay supervisor cuando se aprueba automáticamente

                $saleCount = SaleCount::create([
                    'product_id'       => $product->id,
                    'user_id'          => Auth::id(),
                    'cycle_id'         => $activeCycle->id,
                    'system_quantity'  => $data['system_quantity'],
                    'counted_quantity' => $data['counted_quantity'],
                    'discrepancy'      => $finalDiscrepancy,
                    'status'           => $status,
                    'supervisor_id'    => $supervisorId,
                    'type'             => 'sale',
                    'points_earned'    => 2,
                ]);

                $saleCount->load(['product', 'user', 'cycle']);

                if ($status === 'approved') {
                    $this->createVerificationMovement($product, $data['system_quantity'], $saleCount->created_at);
                }

                $message = $status === 'approved' 
                    ? "Conteo de punto de venta registrado y aprobado automáticamente (sin discrepancia)."
                    : "Conteo de punto de venta registrado.";

                return ['success' => true, 'message' => $message, 'data' => $saleCount];

            } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
                return ['success' => false, 'message' => 'Producto no encontrado.', 'data' => null];
            } catch (\Exception $e) {
                Log::error('Error al registrar conteo de punto de venta', ['error' => $e->getMessage()]);
                return ['success' => false, 'message' => 'Error interno: ' . $e->getMessage(), 'data' => null];
            }
        });
    }

    private function approveOrCorrectSaleCount(SaleCount $saleCount, array $data): array
    {
        $isCorrection = isset($data['corrected_quantity']);
        $finalQuantity = $isCorrection ? (float) $data['corrected_quantity'] : (float) $saleCount->counted_quantity;
        
        $product = $saleCount->product;

        // Stock actual real antes de cualquier modificación
        $stockBefore = (float) $product->lots()->where('quantity', '>', 0)->sum('quantity');
        $finalDiscrepancy = $finalQuantity - $stockBefore;
        
        // Sincronizar system_quantity con el stock al momento de la verificación
        $saleCount->system_quantity = $stockBefore;

        $distributionsCreated = false;

        ProductLotObserver::$skipMovementCreation = true;
        try {
            if (!empty($data['updated_lots'])) {
                foreach ($data['updated_lots'] as $lotData) {
                    $lotToUpdate = ProductLot::find($lotData['id']);
                    if ($lotToUpdate && $lotToUpdate->product_id === $product->id) {
                        $updateData = ['quantity' => $lotData['quantity']];
                        if (isset($lotData['lot_number'])) $updateData['lot_number'] = $lotData['lot_number'];
                        if (isset($lotData['expiration_date'])) $updateData['expiration_date'] = $lotData['expiration_date'];
                        if (isset($lotData['location'])) $updateData['location'] = $lotData['location'];
                        $lotToUpdate->update($updateData);
                        SaleCountDistribution::create([
                            'sale_count_id'  => $saleCount->id,
                            'product_lot_id' => $lotToUpdate->id,
                            'quantity'       => $lotData['quantity'],
                        ]);
                        $distributionsCreated = true;
                    }
                }
            }

            if (!empty($data['new_lots'])) {
                $existingLotsWithCost = ProductLot::where('product_id', $product->id)->where('unit_cost', '>', 0)->get();
                $newLotUnitCost = $existingLotsWithCost->isNotEmpty() ? round($existingLotsWithCost->avg('unit_cost'), 2) : ($product->unit_price ?? 0);

                foreach ($data['new_lots'] as $lotData) {
                    $newLot = ProductLot::create([
                        'product_id'      => $product->id,
                        'lot_number'      => $lotData['lot_number'],
                        'expiration_date' => $lotData['expiration_date'],
                        'location'        => $lotData['location'] ?? null,
                        'quantity'        => $lotData['quantity'],
                        'unit_cost'       => $newLotUnitCost,
                        'supplier_id'     => null,
                    ]);
                    SaleCountDistribution::create([
                        'sale_count_id'  => $saleCount->id,
                        'product_lot_id' => $newLot->id,
                        'quantity'       => $newLot->quantity,
                    ]);
                    $distributionsCreated = true;
                }
            }

            // Si se crearon distribuciones manuales (por lotes actualizados o nuevos):
            if ($distributionsCreated) {
                $stockAfter = (float) $product->lots()->where('quantity', '>', 0)->sum('quantity');
                $product->updateQuietly(['stock' => $stockAfter]);
                $diff = round($stockAfter - $stockBefore, 4);

                if (abs($diff) > 0.0001) {
                    $targetLot = $product->lots()->where('quantity', '>', 0)->first();
                    InventoryMovement::create([
                        'product_id'       => $product->id,
                        'product_lot_id'   => $targetLot?->id,
                        'movement_type'    => $diff > 0 ? 'adjustment' : 'loss',
                        'quantity'         => $diff,
                        'invoice_id'       => null,
                        'supplier_id'      => null,
                        'order_id'         => null,
                        'user_id'          => Auth::id(),
                        'product_count_id' => null,
                        'stock_before'     => $stockBefore,
                        'stock_after'      => $stockAfter,
                        'movement_date'    => now(),
                    ]);
                } else {
                    $this->createVerificationMovement($product, (int) $stockAfter, now());
                }
            } else {
                // Si no se creó ninguna distribución manual:
                if (abs($finalDiscrepancy) < 0.0001) {
                    $stockAfter = $stockBefore;
                    $finalQuantity = (float) $stockBefore;
                    $product->updateQuietly(['stock' => $stockAfter]);
                    $this->createVerificationMovement($product, (int) $stockAfter, now());
                } else {
                    $targetLot = $product->lots()->where('quantity', '>', 0)->orderBy('id', 'desc')->first()
                        ?? $product->lots()->orderBy('id', 'desc')->first();

                    if ($targetLot) {
                        $targetLot->quantity = max(0, $targetLot->quantity + $finalDiscrepancy);
                        $targetLot->saveQuietly();

                        SaleCountDistribution::create([
                            'sale_count_id'  => $saleCount->id,
                            'product_lot_id' => $targetLot->id,
                            'quantity'         => $targetLot->quantity,
                        ]);
                    } else {
                        $targetLot = ProductLot::create([
                            'product_id'      => $product->id,
                            'lot_number'      => 'LOTE-AJUSTE',
                            'expiration_date' => now()->addYears(2)->toDateString(),
                            'quantity'        => max(0, $finalQuantity),
                            'unit_cost'       => $product->unit_cost ?? 0,
                        ]);

                        SaleCountDistribution::create([
                            'sale_count_id'  => $saleCount->id,
                            'product_lot_id' => $targetLot->id,
                            'quantity'         => $targetLot->quantity,
                        ]);
                    }

                    $stockAfter = (float) $product->lots()->where('quantity', '>', 0)->sum('quantity');
                    $product->updateQuietly(['stock' => $stockAfter]);
                    $diff = round($stockAfter - $stockBefore, 4);

                    InventoryMovement::create([
                        'product_id'       => $product->id,
                        'product_lot_id'   => $targetLot->id,
                        'movement_type'    => $diff > 0 ? 'adjustment' : 'loss',
                        'quantity'         => $diff,
                        'invoice_id'       => null,
                        'supplier_id'      => null,
                        'order_id'         => null,
                        'user_id'          => Auth::id(),
                        'product_count_id' => null,
                        'stock_before'     => $stockBefore,
                        'stock_after'      => $stockAfter,
                        'movement_date'    => now(),
                    ]);
                }
            }
        } finally {
            ProductLotObserver::$skipMovementCreation = false;
        }

        $originalCounted = (float) $saleCount->getOriginal('counted_quantity');
        $correctionDiff = $isCorrection ? abs($originalCounted - $finalQuantity) : 0;
        
        $errorPenaltyType = null;
        $penaltyPoints = 0;

        if ($finalDiscrepancy == 0 && abs($originalCounted - $stockBefore) > 0.0001) {
            $errorPenaltyType = 'false_discrepancy';
            $penaltyPoints = 20;
        } elseif ($isCorrection && $correctionDiff > 0.0001) {
            $errorPenaltyType = 'wrong_discrepancy';
            $penaltyPoints = 10;
        }

        $saleCount->counted_quantity      = $finalQuantity;
        $saleCount->discrepancy           = $finalDiscrepancy;
        $saleCount->status                = 'approved';
        $saleCount->supervisor_id         = Auth::id();
        $saleCount->correction_difference = $correctionDiff;
        $saleCount->error_penalty_type    = $errorPenaltyType;
        $saleCount->penalty_points        = $penaltyPoints;
        $saleCount->save();

        $saleCount->load(['product', 'user', 'distributions.productLot']);
        $message = $finalDiscrepancy == 0
            ? "Conteo de punto de venta verificado: coincide con sistema para '{$product->name}'. No se realizó ajuste."
            : "Ajuste de punto de venta para '{$product->name}' aplicado.";
        return ['success' => true, 'message' => $message, 'data' => $saleCount];
    }

    private function rejectSaleCount(SaleCount $saleCount): array
    {
        $saleCount->update(['status' => 'rejected', 'supervisor_id' => Auth::id()]);
        $saleCount->load(['product', 'user']);
        return ['success' => true, 'message' => "Conteo de punto de venta para '{$saleCount->product->name}' rechazado.", 'data' => $saleCount];
    }

    public function processSaleCountAction(SaleCount $saleCount, string $action, array $data = []): array
    {
        if ($saleCount->status !== 'pending') {
            return ['success' => false, 'message' => "Este conteo de punto de venta ya fue procesado. Estado actual: {$saleCount->status}", 'data' => null];
        }

        return DB::transaction(function () use ($saleCount, $action, $data) {
            try {
                if ($action === 'approve' || ($action === 'reject' && isset($data['corrected_quantity']))) {
                    return $this->approveOrCorrectSaleCount($saleCount, $data);
                } elseif ($action === 'reject') {
                    return $this->rejectSaleCount($saleCount);
                }
                return ['success' => false, 'message' => 'Acción no válida.', 'data' => null];
            } catch (\Exception $e) {
                Log::error('Error procesando acción de conteo de punto de venta', ['sale_count_id' => $saleCount->id, 'error' => $e->getMessage()]);
                return ['success' => false, 'message' => 'Error al procesar la acción: ' . $e->getMessage(), 'data' => null];
            }
        });
    }

    public function updateDiscrepancy($model, $newDiscrepancy): array
    {
        return DB::transaction(function () use ($model, $newDiscrepancy) {
            try {
                $oldDiscrepancy = (float) $model->discrepancy;
                $difference = (float) $newDiscrepancy - $oldDiscrepancy;

                if ($difference === 0.0) {
                    return [
                        'success' => true,
                        'message' => 'No hay cambios en la discrepancia.',
                        'data' => $model
                    ];
                }

                // Actualizar el registro del conteo
                $model->discrepancy = $newDiscrepancy;
                $model->counted_quantity = (float) $model->system_quantity + (float) $newDiscrepancy;
                $model->save();

                $product = $model->product;
                if ($product) {
                    // Si el conteo ya está aprobado, sincronizar el lote y registrar movimiento en Kardex
                    if ($model->status === 'approved') {
                        $targetLot = null;
                        
                        // Buscar si existe un lote asociado en distribuciones
                        if ($model->relationLoaded('distributions') && $model->distributions->isNotEmpty()) {
                            $targetLot = $model->distributions->first()->productLot;
                        } elseif (method_exists($model, 'distributions') && $model->distributions()->exists()) {
                            $dist = $model->distributions()->with('productLot')->first();
                            $targetLot = $dist?->productLot;
                        }
                        
                        if (!$targetLot) {
                            $targetLot = $product->lots()->where('quantity', '>', 0)->orderBy('id', 'desc')->first()
                                ?? $product->lots()->orderBy('id', 'desc')->first();
                        }

                        $stockBefore = (float) $product->lots()->sum('quantity');

                        if ($targetLot) {
                            $targetLot->quantity = max(0, $targetLot->quantity + $difference);
                            ProductLot::withoutEvents(function () use ($targetLot) {
                                $targetLot->save();
                            });
                        } else {
                            $targetLot = ProductLot::create([
                                'product_id'      => $product->id,
                                'lot_number'      => 'LOTE-AJUSTE',
                                'expiration_date' => now()->addYears(2)->toDateString(),
                                'quantity'        => max(0, (int) $difference),
                                'unit_cost'       => $product->unit_cost ?? 0,
                            ]);
                        }

                        $stockAfter = (float) $product->lots()->sum('quantity');
                        Product::withoutEvents(function () use ($product, $stockAfter) {
                            $product->update(['stock' => $stockAfter]);
                        });

                        // Registrar movimiento de ajuste en trazabilidad
                        InventoryMovement::create([
                            'product_id'     => $product->id,
                            'product_lot_id' => $targetLot?->id,
                            'movement_type'  => $difference > 0 ? 'adjustment' : 'loss',
                            'quantity'       => $difference,
                            'invoice_id'     => null,
                            'supplier_id'    => null,
                            'order_id'       => null,
                            'user_id'        => Auth::id(),
                            'stock_before'   => $stockBefore,
                            'stock_after'    => $stockAfter,
                            'movement_date'  => now(),
                        ]);
                    }
                }

                return [
                    'success' => true,
                    'message' => 'Discrepancia y trazabilidad de inventario actualizadas correctamente.',
                    'data' => $model
                ];

            } catch (\Exception $e) {
                Log::error('Error en updateDiscrepancy', [
                    'model_id' => $model->id,
                    'new_discrepancy' => $newDiscrepancy,
                    'error' => $e->getMessage()
                ]);
                throw $e;
            }
        });
    }
}
