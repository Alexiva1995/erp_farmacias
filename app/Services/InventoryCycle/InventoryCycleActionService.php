<?php

namespace App\Services\InventoryCycle;

use App\Models\InventoryCycle;
use App\Models\Product;
use App\Models\ProductCount;
use App\Models\ProductLot;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class InventoryCycleActionService
{
    /**
     * Crea un nuevo conteo de inventario
     */
    public function createProductCount(int $productId, array $data): array
    {
        return DB::transaction(function () use ($productId, $data) {
            try {
                // Verificar que el producto existe
                $product = Product::findOrFail($productId);

                // Obtener el ciclo de inventario activo
                $activeCycle = $this->getActiveCycle();
                if (!$activeCycle) {
                    return [
                        'success' => false,
                        'message' => 'No existe un ciclo de inventario activo. Contacte al administrador.',
                        'data' => null
                    ];
                }

                // Calcular el stock actual del sistema (lotes válidos)
                $systemStock = $data['system_quantity'];
                // Verificar que el código de barras coincida (si el producto tiene uno)
                if ($product->barcode && $product->barcode !== $data['barcode']) {
                    return [
                        'success' => false,
                        'message' => 'El código de barras no coincide con el producto seleccionado.',
                        'data' => null
                    ];
                }

                // Validar que la discrepancia calculada coincida
                $expectedDiscrepancy = $data['counted_quantity'] - $systemStock;
                if ((int) $expectedDiscrepancy !== (int) $data['discrepancy']) {
                    Log::warning('Discrepancia no coincide con el cálculo del backend', [
                        'product_id' => $productId,
                        'frontend_system_stock' => $systemStock,
                        'counted_quantity' => $data['counted_quantity'],
                        'frontend_discrepancy' => $data['discrepancy'],
                        'backend_discrepancy' => $expectedDiscrepancy
                    ]);

                    // Confiamos en el cálculo del backend como fuente de verdad final.
                    $data['discrepancy'] = $expectedDiscrepancy;
                }

                // Crear el registro de conteo
                $productCount = ProductCount::create([
                    'product_id' => $product->id,
                    'user_id' => Auth::id(),
                    'cycle_id' => $activeCycle->id,
                    'barcode_scanned' => $data['barcode'],
                    'system_quantity' => $systemStock, // <-- Se guarda el valor correcto
                    'counted_quantity' => $data['counted_quantity'],
                    'discrepancy' => $data['discrepancy'],
                    'status' => 'pending',
                    'product_lot_id' => null,
                    'count_date' => now(),
                ]);

                // Cargar las relaciones para la respuesta
                $productCount->load(['product', 'user', 'cycle']);

                Log::info('Conteo de inventario registrado', [
                    'product_count_id' => $productCount->id,
                    'product_id' => $product->id,
                    'product_name' => $product->name,
                    'cycle_id' => $activeCycle->id,
                    'cycle_status' => $activeCycle->status,
                    'user_id' => Auth::id(),
                    'system_quantity' => $systemStock,
                    'counted_quantity' => $data['counted_quantity'],
                    'discrepancy' => $data['discrepancy']
                ]);

                return [
                    'success' => true,
                    'message' => "Conteo registrado exitosamente.",
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

    /**
     * Obtiene el ciclo de inventario activo
     */
    private function getActiveCycle(): ?InventoryCycle
    {
        return InventoryCycle::where('status', 'active')->first();
    }

    /**
     * Calcula el stock actual del producto basado en lotes válidos
     */
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

    /**
     * Procesa la acción de aprobar o rechazar un conteo
     */
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
                // Si es aprobación o corrección, usar el flujo de actualización
                if ($action === 'approve' || ($action === 'reject' && isset($data['corrected_quantity']))) {
                    return $this->approveOrCorrectCount($productCount, $data);
                }
                // Si es un rechazo simple sin corrección (no hace cambios en stock)
                elseif ($action === 'reject') {
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

    /**
     * Aprueba un conteo y actualiza el inventario del lote
     */
    private function approveOrCorrectCount(ProductCount $productCount, array $data): array
    {
        $isCorrection = isset($data['corrected_quantity']);
        $finalQuantity = $isCorrection ? $data['corrected_quantity'] : $productCount->counted_quantity;
        $finalDiscrepancy = $finalQuantity - $productCount->system_quantity;

        // 1. Actualizar el lote si se proporcionó
        if (!empty($data['lot'])) {
            $lotData = $data['lot'];
            $lotToUpdate = ProductLot::find($lotData['lot_id']);

            if ($lotToUpdate && $lotToUpdate->product_id === $productCount->product_id) {
                $lotToUpdate->update(['quantity' => $lotData['quantity']]);
                // El Observer se encargará de actualizar el stock del producto.

                // Guardar la referencia al lote que fue modificado
                $productCount->product_lot_id = $lotToUpdate->id;
            } else {
                Log::warning('Se intentó actualizar un lote no encontrado o no perteneciente al producto.', [
                    'lot_data' => $lotData,
                    'product_id' => $productCount->product_id,
                ]);
            }
        }

        // 2. Actualizar el registro del conteo
        $productCount->counted_quantity = $finalQuantity;
        $productCount->discrepancy = $finalDiscrepancy;
        $productCount->status = 'approved';
        $productCount->supervisor_id = Auth::id();
        $productCount->save();

        $productCount->load(['product', 'user', 'productLot']);

        $message = "Ajuste de inventario aplicado exitosamente a '{$productCount->product->name}'.";

        return [
            'success' => true,
            'message' => $message,
            'data' => $productCount
        ];
    }

    /**
     * Rechaza un conteo
     */
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

    /**
     * Actualiza la cantidad en el lote basado en el conteo
     */
    private function updateLotQuantity(ProductCount $productCount): void
    {
        $productLot = ProductLot::findOrFail($productCount->product_lot_id);

        // La cantidad contada puede ser positiva (suma) o negativa (resta)
        $countedQuantity = $productCount->counted_quantity;

        // Si la cantidad es positiva, se suma al inventario
        // Si la cantidad es negativa, se resta del inventario
        $newQuantity = $productLot->quantity + $countedQuantity;

        // Validar que la cantidad final no sea negativa (opcional)
        if ($newQuantity < 0) {
            Log::warning('Cantidad del lote resultaría negativa', [
                'product_lot_id' => $productLot->id,
                'current_quantity' => $productLot->quantity,
                'counted_quantity' => $countedQuantity,
                'calculated_quantity' => $newQuantity
            ]);

            // Opcional: Podrías lanzar una excepción o permitir cantidades negativas
            // Para este caso, permitimos cantidades negativas pero las registramos
        }

        // Actualizar la cantidad en el lote
        $productLot->update([
            'quantity' => $newQuantity
        ]);

        // Actualizar también system_quantity y discrepancy en el conteo
        $productCount->update([
            'system_quantity' => $productLot->quantity - $countedQuantity, // La cantidad anterior del sistema
            'discrepancy' => $countedQuantity // La diferencia encontrada
        ]);

        Log::info('Cantidad del lote actualizada', [
            'product_lot_id' => $productLot->id,
            'lot_number' => $productLot->lot_number ?? 'N/A',
            'previous_quantity' => $productLot->quantity - $countedQuantity,
            'counted_quantity' => $countedQuantity,
            'new_quantity' => $newQuantity,
            'product_count_id' => $productCount->id
        ]);
    }

    /**
     * Obtiene estadísticas de conteos por estado
     */
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

    /**
     * Verifica si un conteo puede ser procesado
     */
    public function canProcessCount(ProductCount $productCount): bool
    {
        return $productCount->status === 'pending';
    }

    /**
     * Obtiene conteos pendientes para un supervisor
     */
    public function getPendingCountsForSupervisor(int $supervisorId): \Illuminate\Database\Eloquent\Collection
    {
        return ProductCount::with(['product', 'user', 'productLot'])
            ->where('supervisor_id', $supervisorId)
            ->where('status', 'pending')
            ->orderBy('created_at', 'desc')
            ->get();
    }

    /**
     * Verifica si existe un ciclo de inventario activo
     */
    public function hasActiveCycle(): bool
    {
        return InventoryCycle::where('status', 'active')->exists();
    }

    /**
     * Obtiene el ciclo de inventario activo (método público)
     */
    public function getActiveCycleInfo(): ?InventoryCycle
    {
        return $this->getActiveCycle();
    }
}
