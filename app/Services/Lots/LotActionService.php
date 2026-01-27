<?php

namespace App\Services\Lots;

use App\Models\Product;
use App\Models\ProductLot;
use App\Rules\ValidateLotQuantity;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class LotActionService
{
    public function createLot(array $data)
    {
        $validator = Validator::make($data, [
            'quantity' => ['required', 'integer', 'min:1', new ValidateLotQuantity($data['product_id'])],
        ]);

        if ($validator->fails()) {
            throw new \Illuminate\Validation\ValidationException($validator);
        }

        return ProductLot::create($data);
    }

    public function updateLot(ProductLot $productLot, array $data)
    {
        if (isset($data['quantity'])) {
            $validator = Validator::make($data, [
                'quantity' => ['required', 'integer', 'min:0', new ValidateLotQuantity($productLot->product_id, $productLot->id)],
            ]);

            if ($validator->fails()) {
                throw new \Illuminate\Validation\ValidationException($validator);
            }
        }

        $productLot->update($data);
        return $productLot;
    }

    public function deleteLot(ProductLot $productLot)
    {
        $productLot->update(['quantity' => 0]);
    }

    /**
     * Transfiere todas las referencias de un lote a otro lote antes de eliminarlo.
     *
     * @param int $lotToDeleteId ID del lote que se va a eliminar
     * @param int $lotToKeepId ID del lote que se mantiene
     * @return void
     * @throws \Exception Si ocurre un error durante la transferencia
     */
    private function transferLotReferences(int $lotToDeleteId, int $lotToKeepId): void
    {
        try {
            // Deshabilitar temporalmente las verificaciones de foreign key para permitir actualizaciones
            DB::statement('SET FOREIGN_KEY_CHECKS=0');

            // Transferir todas las referencias usando actualizaciones directas (más rápido que contar primero)
            // Usar update con whereExists para evitar actualizaciones innecesarias
            DB::table('expirations')
                ->where('product_lot_id', $lotToDeleteId)
                ->update(['product_lot_id' => $lotToKeepId]);

            DB::table('inventory_movements')
                ->where('product_lot_id', $lotToDeleteId)
                ->update(['product_lot_id' => $lotToKeepId]);

            DB::table('expired_logs')
                ->where('lot_id', $lotToDeleteId)
                ->update(['lot_id' => $lotToKeepId]);

            DB::table('price_adjustment_logs')
                ->where('lot_id', $lotToDeleteId)
                ->update(['lot_id' => $lotToKeepId]);

            DB::table('product_distributions')
                ->where('product_lot_id', $lotToDeleteId)
                ->update(['product_lot_id' => $lotToKeepId]);

            // Transferir product_counts (si todavía tiene la columna)
            if (DB::getSchemaBuilder()->hasColumn('product_counts', 'product_lot_id')) {
                DB::table('product_counts')
                    ->where('product_lot_id', $lotToDeleteId)
                    ->update(['product_lot_id' => $lotToKeepId]);
            }

            // Transferir expiration_offer_product_lot (tabla pivot)
            // Primero verificar si ya existe la relación para evitar duplicados
            $existingOffers = DB::table('expiration_offer_product_lot')
                ->where('product_lot_id', $lotToKeepId)
                ->pluck('expiration_offer_id')
                ->toArray();

            $offersToTransfer = DB::table('expiration_offer_product_lot')
                ->where('product_lot_id', $lotToDeleteId)
                ->get();

            foreach ($offersToTransfer as $offer) {
                // Solo transferir si no existe ya la relación
                if (!in_array($offer->expiration_offer_id, $existingOffers)) {
                    DB::table('expiration_offer_product_lot')
                        ->where('id', $offer->id)
                        ->update(['product_lot_id' => $lotToKeepId]);
                } else {
                    // Si ya existe, eliminar el duplicado
                    DB::table('expiration_offer_product_lot')
                        ->where('id', $offer->id)
                        ->delete();
                }
            }

            DB::table('invoice_count_distributions')
                ->where('product_lot_id', $lotToDeleteId)
                ->update(['product_lot_id' => $lotToKeepId]);

            // Transferir sale_count_distributions (si existe)
            if (DB::getSchemaBuilder()->hasTable('sale_count_distributions')) {
                DB::table('sale_count_distributions')
                    ->where('product_lot_id', $lotToDeleteId)
                    ->update(['product_lot_id' => $lotToKeepId]);
            }

            // Rehabilitar las verificaciones de foreign key
            DB::statement('SET FOREIGN_KEY_CHECKS=1');

        } catch (\Exception $e) {
            // Asegurarse de rehabilitar las verificaciones de foreign key incluso si hay error
            DB::statement('SET FOREIGN_KEY_CHECKS=1');
            throw new \Exception(
                "Error al transferir referencias del lote ID {$lotToDeleteId} al lote ID {$lotToKeepId}: " . $e->getMessage(),
                0,
                $e
            );
        }
    }

    /**
     * Elimina todos los lotes con cantidad 0, pero siempre deja al menos un lote por producto.
     * 
     * Reglas:
     * - Si el producto tiene otro lote con unidades (>0), elimina TODOS los lotes en 0 de ese producto.
     * - Si el producto solo tiene lotes en 0, deja el lote en 0 más actual (más reciente) y elimina los demás.
     * - Si el producto solo tiene un lote (aunque esté en 0), no se elimina.
     * - Antes de eliminar un lote, transfiere todas sus referencias al lote que se mantiene.
     *
     * @return int Número de lotes eliminados
     * @throws \Exception Si ocurre un error durante la eliminación
     */
    public function deleteLotsWithZeroQuantity(): int
    {
        $deletedCount = 0;
        $errors = [];
        
        try {
            // Obtener todos los productos únicos que tienen lotes con cantidad 0
            $productIds = ProductLot::where('quantity', '<=', 0)
                ->distinct()
                ->pluck('product_id')
                ->toArray();
            
            if (empty($productIds)) {
                return 0;
            }
            
            // Procesar cada producto en una transacción separada para evitar timeouts
            foreach ($productIds as $productId) {
                try {
                    DB::beginTransaction();
                    
                    // Obtener todos los lotes del producto ordenados por fecha de creación descendente (más reciente primero)
                    // No usar lockForUpdate para evitar timeouts de bloqueo
                    $allLots = ProductLot::where('product_id', $productId)
                        ->orderBy('created_at', 'desc')
                        ->orderBy('id', 'desc')
                        ->get();
                    
                    // Separar lotes en 0 y lotes con cantidad > 0
                    $zeroLots = $allLots->filter(function ($lot) {
                        return (int)$lot->quantity <= 0;
                    })->values();
                    
                    $positiveLots = $allLots->filter(function ($lot) {
                        return (int)$lot->quantity > 0;
                    });
                    
                    $totalLots = $allLots->count();
                    $zeroLotsCount = $zeroLots->count();
                    $positiveLotsCount = $positiveLots->count();
                    
                    // Si el producto solo tiene un lote, no lo eliminamos (aunque esté en 0)
                    if ($totalLots === 1) {
                        DB::commit();
                        continue;
                    }
                    
                    // Determinar qué lote se mantiene
                    $lotToKeep = null;
                    
                    // Si el producto tiene lotes con cantidad > 0, usar el primero de esos
                    if ($positiveLotsCount > 0) {
                        $lotToKeep = $positiveLots->first();
                    } 
                    // Si el producto solo tiene lotes en 0, usar el más reciente
                    else if ($zeroLotsCount === $totalLots && $zeroLotsCount > 1) {
                        $lotToKeep = $zeroLots->first();
                    }
                    
                    // Si el producto tiene lotes con cantidad > 0, eliminar TODOS los lotes en 0
                    if ($positiveLotsCount > 0 && $lotToKeep) {
                        foreach ($zeroLots as $lot) {
                            try {
                                $lotId = $lot->id;
                                
                                // Transferir todas las referencias antes de eliminar
                                $this->transferLotReferences($lotId, $lotToKeep->id);
                                
                                // Eliminar el lote usando SQL directo para evitar el Observer
                                ProductLot::withoutEvents(function () use ($lotId) {
                                    DB::table('product_lots')->where('id', $lotId)->delete();
                                });
                                
                                $deletedCount++;
                            } catch (\Exception $lotException) {
                                $errors[] = "Producto ID {$productId}, Lote ID {$lotId}: " . $lotException->getMessage();
                                // Continuar con el siguiente lote en lugar de hacer rollback completo
                            }
                        }
                    } 
                    // Si el producto solo tiene lotes en 0, dejar el más actual (más reciente) y eliminar los demás
                    else if ($zeroLotsCount === $totalLots && $zeroLotsCount > 1 && $lotToKeep) {
                        // El primer lote es el más reciente (ordenado por created_at DESC)
                        $mostRecentLot = $zeroLots->first();
                        
                        foreach ($zeroLots as $lot) {
                            if ($lot->id !== $mostRecentLot->id) {
                                try {
                                    $lotId = $lot->id;
                                    
                                    // Transferir todas las referencias antes de eliminar
                                    $this->transferLotReferences($lotId, $mostRecentLot->id);
                                    
                                    // Eliminar el lote usando SQL directo para evitar el Observer
                                    ProductLot::withoutEvents(function () use ($lotId) {
                                        DB::table('product_lots')->where('id', $lotId)->delete();
                                    });
                                    
                                    $deletedCount++;
                                } catch (\Exception $lotException) {
                                    $errors[] = "Producto ID {$productId}, Lote ID {$lotId}: " . $lotException->getMessage();
                                    // Continuar con el siguiente lote
                                }
                            }
                        }
                    }
                    
                    DB::commit();
                    
                } catch (\Exception $e) {
                    // Si hay error con un producto específico, hacer rollback solo de ese producto
                    DB::rollBack();
                    $errors[] = "Producto ID {$productId}: " . $e->getMessage();
                    // Continuar con el siguiente producto en lugar de detener todo el proceso
                }
            }
            
            // Si hubo errores pero se procesaron algunos lotes, lanzar excepción con detalles
            if (!empty($errors) && $deletedCount === 0) {
                throw new \Exception(
                    "No se pudieron eliminar lotes. Errores: " . implode("; ", array_slice($errors, 0, 5)) . 
                    (count($errors) > 5 ? " (y " . (count($errors) - 5) . " más)" : "")
                );
            } elseif (!empty($errors)) {
                // Si hubo errores pero se eliminaron algunos, solo loguear
                \Log::warning('Algunos lotes no se pudieron eliminar', ['errors' => $errors]);
            }
            
            return $deletedCount;
            
        } catch (\Exception $e) {
            throw new \Exception(
                "Error al eliminar lotes con cantidad 0: " . $e->getMessage() . 
                ($deletedCount > 0 ? " (Se eliminaron {$deletedCount} lotes antes del error)" : ""),
                0,
                $e
            );
        }
    }

    public function batchUpdateLots(array $data)
    {
        $productId = $data['product_id'];
        $lotsData = $data['lots'];
        $errors = [];

        DB::beginTransaction();

        try {
            $product = Product::find($productId);
            if (!$product) {
                return ['errors' => ['product' => 'Producto no encontrado.']];
            }

            $lotsToUpdate = [];
            $lotsToCreate = [];
            $lotsToDelete = [];

            foreach ($lotsData as $index => $lotData) {
                $isNew = !isset($lotData['id']) || $lotData['id'] <= 0;
                $isMarkedForDeletion = isset($lotData['quantity']) && (int) $lotData['quantity'] === 0;

                if ($isNew && !$isMarkedForDeletion) {
                    $lotsToCreate[] = ['index' => $index, 'data' => $lotData];
                } elseif (!$isNew && $isMarkedForDeletion) {
                    $lotsToDelete[] = ['index' => $index, 'data' => $lotData];
                } elseif (!$isNew && !$isMarkedForDeletion) {
                    $lotsToUpdate[] = ['index' => $index, 'data' => $lotData];
                }
            }
            $currentExistingLotsSum = ProductLot::where('product_id', $productId)->sum('quantity');
            $deletedQuantity = 0;
            foreach ($lotsToDelete as $lot) {
                $existingLot = ProductLot::find($lot['data']['id']);
                if ($existingLot) {
                    $deletedQuantity += $existingLot->quantity;
                }
            }
            $newTotalQuantity = $currentExistingLotsSum - $deletedQuantity;
            foreach ($lotsToUpdate as $lot) {
                $existingLot = ProductLot::find($lot['data']['id']);
                if ($existingLot) {
                    $newTotalQuantity = $newTotalQuantity - $existingLot->quantity + (int) $lot['data']['quantity'];
                }
            }
            foreach ($lotsToCreate as $lot) {
                $newTotalQuantity += (int) $lot['data']['quantity'];
            }
            if ($newTotalQuantity !== $product->stock) {
                $errors['stock'] = "La cantidad total de lotes ({$newTotalQuantity}) debe ser igual al stock del producto ({$product->stock}).";
            }
            $allLotsToValidate = array_merge($lotsToCreate, $lotsToUpdate);

            foreach ($allLotsToValidate as $lotItem) {
                $index = $lotItem['index'];
                $lotData = $lotItem['data'];
                $isNew = !isset($lotData['id']) || $lotData['id'] <= 0;

                $rules = [
                    'lot_number' => 'required|string|max:255',
                    'quantity' => 'required|integer|min:1',
                    'expiration_date' => 'required|date',
                    'unit_cost' => $isNew ? 'nullable|numeric|min:0' : 'required|numeric|min:0',
                    'location' => 'nullable|string|max:100',
                ];

                $validator = Validator::make($lotData, $rules);

                if ($validator->fails()) {
                    $errors["lote_{$index}"] = $validator->errors()->toArray();
                    continue;
                }
            }

            if (!empty($errors)) {
                DB::rollBack();
                return ['errors' => $errors];
            }

            foreach ($lotsToDelete as $lotItem) {
                $lotData = $lotItem['data'];
                $productLot = ProductLot::find($lotData['id']);
                if ($productLot) {
                    $productLot->update([
                        'quantity' => 0
                    ]);
                }
            }

            foreach ($lotsToUpdate as $lotItem) {
                $lotData = $lotItem['data'];
                $productLot = ProductLot::find($lotData['id']);
                if ($productLot) {
                    $productLot->update([
                        'lot_number' => $lotData['lot_number'],
                        'quantity' => $lotData['quantity'],
                        'expiration_date' => $lotData['expiration_date'],
                        'unit_cost' => $lotData['unit_cost'],
                        'location' => $lotData['location'] ?? null,
                    ]);
                }
            }

            foreach ($lotsToCreate as $lotItem) {
                $lotData = $lotItem['data'];
                ProductLot::create([
                    'product_id' => $productId,
                    'lot_number' => $lotData['lot_number'],
                    'quantity' => $lotData['quantity'],
                    'expiration_date' => $lotData['expiration_date'],
                    'unit_cost' => $lotData['unit_cost'] ?? null,
                    'location' => $lotData['location'] ?? null,
                ]);
            }

            DB::commit();
            return ['success' => true];

        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }
}
