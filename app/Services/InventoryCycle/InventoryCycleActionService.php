<?php

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

                $systemStock = $data['system_quantity'];
                $allowWithoutBarcode = $data['allow_without_barcode'] ?? false;

                // Solo validar código de barras si no se permite sin código de barras
                if (!$allowWithoutBarcode) {
                    if ($product->barcode && isset($data['barcode']) && $product->barcode !== $data['barcode']) {
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

                // Si no hay discrepancia, aprobar automáticamente sin supervisor
                $finalDiscrepancy = $data['discrepancy'];
                $status = ($finalDiscrepancy == 0) ? 'approved' : 'pending';
                $supervisorId = null; // No hay supervisor cuando se aprueba automáticamente

                $productCount = ProductCount::create([
                    'product_id' => $product->id,
                    'user_id' => Auth::id(),
                    'cycle_id' => $activeCycle->id,
                    'barcode_scanned' => $allowWithoutBarcode ? null : ($data['barcode'] ?? null),
                    'system_quantity' => $systemStock,
                    'counted_quantity' => $data['counted_quantity'],
                    'discrepancy' => $finalDiscrepancy,
                    'status' => $status,
                    'supervisor_id' => $supervisorId,
                    'product_lot_id' => null,
                    'count_date' => now(),
                ]);

                $productCount->load(['product', 'user', 'cycle']);

                if ($status === 'approved') {
                    $this->createVerificationMovement($product, $systemStock, $productCount->created_at);
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

    private function getActiveCycle(): ?InventoryCycle
    {
        return InventoryCycle::where('status', 'active')->first();
    }

    /**
     * Crea un movimiento de inventario tipo ajuste cuando el conteo físico coincide con el stock en sistema.
     */
    private function createVerificationMovement(Product $product, int $stockQuantity, \DateTimeInterface $movementDate): void
    {
        InventoryMovement::create([
            'product_id' => $product->id,
            'product_lot_id' => null,
            'movement_type' => 'adjustment',
            'quantity' => 0,
            'user_id' => Auth::id(),
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

        $today = now()->startOfDay();

        return $product->lots
            ->filter(function ($lot) use ($today) {
                return $lot->expiration_date &&
                    $lot->expiration_date >= $today &&
                    $lot->quantity > 0;
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
        $finalQuantity = $isCorrection ? $data['corrected_quantity'] : $productCount->counted_quantity;
        $finalDiscrepancy = $finalQuantity - $productCount->system_quantity;

        $product = $productCount->product;
        $distributionsCreated = false;

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

        // Si no se creó ninguna distribución, el conteo físico coincide con el sistema.
        // Se comporta igual que discrepancia=0 al momento del registro:
        // forzar discrepancia a 0 y crear movimiento de verificación.
        if (!$distributionsCreated) {
            $realCurrentStock = (int) $product->lots()->sum('quantity');
            $finalQuantity    = $realCurrentStock;
            $finalDiscrepancy = 0;
            
            // También actualizamos el system_quantity en el registro para que la discrepancia sea 0 real.
            $productCount->system_quantity = $realCurrentStock;
            
            $this->createVerificationMovement($product, $realCurrentStock, now());
        }

        $productCount->counted_quantity    = $finalQuantity;
        $productCount->discrepancy         = $finalDiscrepancy;
        $productCount->status              = 'approved';
        $productCount->supervisor_id       = Auth::id();
        $productCount->correction_difference = $isCorrection
            ? abs($productCount->getOriginal('counted_quantity') - $finalQuantity)
            : 0;
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

    private function updateLotQuantity(ProductCount $productCount): void
    {
        $productLot = ProductLot::findOrFail($productCount->product_lot_id);

        $countedQuantity = $productCount->counted_quantity;
        $newQuantity = $productLot->quantity + $countedQuantity;

        if ($newQuantity < 0) {
            Log::warning('Cantidad del lote resultaría negativa', [
                'product_lot_id' => $productLot->id,
                'current_quantity' => $productLot->quantity,
                'counted_quantity' => $countedQuantity,
                'calculated_quantity' => $newQuantity
            ]);
        }

        $productLot->update([
            'quantity' => $newQuantity
        ]);

        $productCount->update([
            'system_quantity' => $productLot->quantity - $countedQuantity,
            'discrepancy' => $countedQuantity
        ]);
    }

    public function getCountStatistics(): array
    {
        $statistics = ProductCount::selectRaw('
            status,
            COUNT(*) as count,
            SUM(CASE WHEN counted_quantity > 0 THEN counted_quantity ELSE 0 END) as positive_counts,
            SUM(CASE WHEN counted_quantity < 0 THEN counted_quantity ELSE 0 END) as negative_counts,
            AVG(counted_quantity) as average_count,
            SUM(ABS(discrepancy)) as total_discrepancies
        ')
            ->groupBy('status')
            ->get()
            ->keyBy('status');

        return [
            'pending' => $statistics->get('pending', (object) [
                'count' => 0,
                'positive_counts' => 0,
                'negative_counts' => 0,
                'average_count' => 0,
                'total_discrepancies' => 0
            ]),
            'approved' => $statistics->get('approved', (object) [
                'count' => 0,
                'positive_counts' => 0,
                'negative_counts' => 0,
                'average_count' => 0,
                'total_discrepancies' => 0
            ]),
            'rejected' => $statistics->get('rejected', (object) [
                'count' => 0,
                'positive_counts' => 0,
                'negative_counts' => 0,
                'average_count' => 0,
                'total_discrepancies' => 0
            ]),
        ];
    }

    public function canProcessCount(ProductCount $productCount): bool
    {
        return $productCount->status === 'pending';
    }

    public function getPendingCountsForSupervisor(int $supervisorId): \Illuminate\Database\Eloquent\Collection
    {
        return ProductCount::with(['product', 'user', 'productLot'])
            ->where('supervisor_id', $supervisorId)
            ->where('status', 'pending')
            ->orderBy('created_at', 'desc')
            ->get();
    }

    public function hasActiveCycle(): bool
    {
        return InventoryCycle::where('status', 'active')->exists();
    }

    public function getActiveCycleInfo(): ?InventoryCycle
    {
        return $this->getActiveCycle();
    }

    public function createInvoiceCount(int $productId, array $data): array
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

                $invoiceCount = InvoiceCount::create([
                    'product_id' => $product->id,
                    'user_id' => Auth::id(),
                    'cycle_id' => $activeCycle->id,
                    'system_quantity' => $data['system_quantity'],
                    'counted_quantity' => $data['counted_quantity'],
                    'discrepancy' => $finalDiscrepancy,
                    'status' => $status,
                    'supervisor_id' => $supervisorId,
                    'type' => 'invoice',
                ]);

                $invoiceCount->load(['product', 'user', 'cycle']);

                if ($status === 'approved') {
                    $this->createVerificationMovement($product, $data['system_quantity'], $invoiceCount->created_at);
                }

                $message = $status === 'approved' 
                    ? "Conteo de factura registrado y aprobado automáticamente (sin discrepancia)."
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
        $finalQuantity = $isCorrection ? $data['corrected_quantity'] : $invoiceCount->counted_quantity;
        $finalDiscrepancy = $finalQuantity - $invoiceCount->system_quantity;
        $product = $invoiceCount->product;
        $distributionsCreated = false;

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

        if (!$distributionsCreated) {
            $realCurrentStock = (int) $product->lots()->sum('quantity');
            $finalQuantity    = $realCurrentStock;
            $finalDiscrepancy = 0;
            
            $invoiceCount->system_quantity = $realCurrentStock;
            
            $this->createVerificationMovement($product, $realCurrentStock, now());
        }

        $invoiceCount->counted_quantity = $finalQuantity;
        $invoiceCount->discrepancy      = $finalDiscrepancy;
        $invoiceCount->status           = 'approved';
        $invoiceCount->supervisor_id    = Auth::id();
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

    public function closeActiveCycle(): array
    {
        return DB::transaction(function () {
            $activeCycle = $this->getActiveCycle();

            if (!$activeCycle) {
                return ['success' => false, 'message' => 'No se encontró ningún ciclo de inventario activo para cerrar.'];
            }

            $hasPendingProductCounts = ProductCount::where('cycle_id', $activeCycle->id)
                ->where('status', 'pending')
                ->exists();

            $hasPendingInvoiceCounts = InvoiceCount::where('cycle_id', $activeCycle->id)
                ->where('status', 'pending')
                ->exists();

            if ($hasPendingProductCounts || $hasPendingInvoiceCounts) {
                return [
                    'success' => false,
                    'message' => 'No se puede cerrar el ciclo. Aún existen conteos pendientes de aprobación o rechazo.'
                ];
            }

            $activeCycle->status = 'closed';
            $activeCycle->end_date = now();
            $activeCycle->save();

            Log::info("Ciclo de inventario cerrado exitosamente.", ['cycle_id' => $activeCycle->id, 'closed_by' => Auth::id()]);

            return ['success' => true, 'message' => 'El ciclo de inventario ha sido cerrado exitosamente.'];
        });
    }

    public function createNewCycle(): array
    {
        if ($this->hasActiveCycle()) {
            return ['success' => false, 'message' => 'Ya existe un ciclo de inventario activo.'];
        }

        $newCycle = InventoryCycle::create([
            'start_date' => now(),
            'end_date' => null,
            'status' => 'active',
            'created_by_id' => Auth::id(),
        ]);

        Log::info("Nuevo ciclo de inventario creado.", ['cycle_id' => $newCycle->id, 'created_by' => Auth::id()]);

        return ['success' => true, 'message' => 'Nuevo ciclo de inventario creado y activado.'];
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

                $SaleCount = SaleCount::create([
                    'product_id' => $product->id,
                    'user_id' => Auth::id(),
                    'cycle_id' => $activeCycle->id,
                    'system_quantity' => $data['system_quantity'],
                    'counted_quantity' => $data['counted_quantity'],
                    'discrepancy' => $finalDiscrepancy,
                    'status' => $status,
                    'supervisor_id' => $supervisorId,
                    'type' => 'sale',
                ]);

                $SaleCount->load(['product', 'user', 'cycle']);

                if ($status === 'approved') {
                    $this->createVerificationMovement($product, $data['system_quantity'], $SaleCount->created_at);
                }

                $message = $status === 'approved' 
                    ? "Conteo de punto de venta registrado y aprobado automáticamente (sin discrepancia)."
                    : "Conteo de punto de venta registrado.";

                return ['success' => true, 'message' => $message, 'data' => $SaleCount];

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
        $finalQuantity = $isCorrection ? $data['corrected_quantity'] : $saleCount->counted_quantity;
        $finalDiscrepancy = $finalQuantity - $saleCount->system_quantity;
        $product = $saleCount->product;
        $distributionsCreated = false;

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

        if (!$distributionsCreated) {
            $realCurrentStock = (int) $product->lots()->sum('quantity');
            $finalQuantity    = $realCurrentStock;
            $finalDiscrepancy = 0;
            
            $saleCount->system_quantity = $realCurrentStock;
            
            $this->createVerificationMovement($product, $realCurrentStock, now());
        }

        $saleCount->counted_quantity = $finalQuantity;
        $saleCount->discrepancy      = $finalDiscrepancy;
        $saleCount->status           = 'approved';
        $saleCount->supervisor_id    = Auth::id();
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
}
