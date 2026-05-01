<?php

namespace App\Services\Returns;

use App\Services\Resources\ResourceService;
use App\Models\Client;
use App\Models\InventoryMovement;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductLot;
use App\Models\ReturnEntry;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Exception;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;

class ReturnsActionService
{
    public function __construct(private ResourceService $resourceService)
    {
    }


    public function searchOrdersReturns(string $searchTerm, array $options): Builder
    {
        try {
            // Normalizar el término de búsqueda para identificación (quitar guiones y puntos)
            \Log::info('Buscando devoluciones para:', ['term' => $searchTerm]);
            $normalizedSearch = preg_replace('/[^A-Za-z0-9]/', '', $searchTerm);

            // Órdenes de las últimas 48 horas para devoluciones
            // Usamos NOW() de MySQL para evitar discrepancias de zona horaria entre PHP y DB
            $query = Order::whereRaw('order_date >= (NOW() - INTERVAL 48 HOUR)')
                ->where('status', Order::COMPLETED)
                ->with(['client', 'details.product.laboratory', 'returns'])
                ->where(function ($q) use ($searchTerm, $normalizedSearch) {
                    // 1. Búsqueda por ID de orden exacto
                    if (is_numeric($searchTerm) && strlen($searchTerm) < 10) {
                        $q->where('id', $searchTerm);
                    }

                    // 2. Búsqueda por identificación del cliente (Normalizada)
                    $q->orWhereHas('client', function ($sub) use ($searchTerm, $normalizedSearch) {
                        $sub->where('identification', 'like', "%{$searchTerm}%")
                            ->orWhere('identification', 'like', "%{$normalizedSearch}%")
                            // Comparar contra identificación normalizada en BD (quitando guiones/puntos)
                            ->orWhereRaw("REPLACE(REPLACE(identification, '.', ''), '-', '') LIKE ?", ["%{$normalizedSearch}%"])
                            // Comparar contra concatenación completa normalizada
                            ->orWhereRaw("REPLACE(REPLACE(CONCAT(COALESCE(identification_type,''), COALESCE(identification,'')), '-', ''), '.', '') LIKE ?", ["%{$normalizedSearch}%"]);
                    });

                    // 3. Búsqueda por nombre del cliente
                    $q->orWhereHas('client', function ($sub) use ($searchTerm) {
                        $sub->where('name', 'like', "%{$searchTerm}%")
                            ->orWhere('last_name', 'like', "%{$searchTerm}%")
                            ->orWhereRaw('CONCAT(COALESCE(name,""), " ", COALESCE(last_name,"")) LIKE ?', ["%{$searchTerm}%"]);
                    });
                });

            if (isset($options['sortBy']) && !empty($options['sortBy'])) {
                $sortBy = $options['sortBy'];
                $orderBy = $options['orderBy'] ?? 'desc';
                $query->orderBy($sortBy, $orderBy);
            } else {
                $query->orderBy('order_date', 'desc');
            }

            \Log::info('SQL de búsqueda devoluciones:', [
                'sql' => $query->toSql(),
                'bindings' => $query->getBindings(),
                'now' => \Carbon\Carbon::now()->toDateTimeString(),
                'sub48' => \Carbon\Carbon::now()->subHours(48)->toDateTimeString()
            ]);

            return $query;
        } catch (\Exception $e) {
            Log::error('searchOrdersReturns error: ' . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
            throw $e;
        }
    }

    public function getProductLotsForReturn($productId)
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

    public function productReturn(Request $request)
    {
        DB::beginTransaction();

        try {
            $orderData = $request->order;
            $productData = $request->product;
            $orderDetail = collect($orderData['details'])->firstWhere('product_id', $productData['id']);
            $returnsQuantity = (int) $request->input('returns_quantity');
            $productLotId = $request->input('product_lot_id');

            if ($returnsQuantity <= 0) {
                throw new Exception('La cantidad a devolver debe ser mayor a cero.');
            }

            if (!$orderDetail) {
                throw new Exception('No se encontró el detalle del producto en la orden.');
            }
            $orderDetail = (object) $orderDetail;

            $priceUsd = (float) ($orderDetail->unit_price_usd ?? 0);
            if (!$priceUsd) {
                $price = (float) ($orderDetail->price ?? 0);
                $currency = strtoupper($orderData['currency'] ?? 'USD');
                $usdConversion = (float) ($orderData['usd_conversion'] ?? 1) ?: 1;
                $priceUsd = $currency === 'USD' ? $price : ($price / $usdConversion);
            }
            $returnAmount = round(($returnsQuantity * $priceUsd) * (100 - (float) ($orderDetail->discount_percentage ?? 0)) / 100, 2);
            $clientData = $orderData['client'];

            if (!$clientData) {
                throw new Exception('No se encontró el cliente asociado a la orden.');
            }

            // La distribución en lotes y el movimiento de inventario se realizan al aprobar en /tpv/returnsSupervisor
            $return = ReturnEntry::create([
                'order_id' => $orderData['id'],
                'generated_by_id' => Auth::id(),
                'product_id' => $productData['id'],
                'quantity' => $returnsQuantity,
                'amount_refunded' => $returnAmount,
                'return_date' => Carbon::now(),
                'status' => null
            ]);

            DB::commit();
            return [
                'success' => true,
                'message' => 'Devolución procesada con éxito.',
            ];
        } catch (Exception $e) {
            DB::rollBack();
            throw new Exception($e->getMessage());
        }
    }

    public function updateStatus(ReturnEntry $ReturnEntry, string $status): ReturnEntry
    {
        DB::beginTransaction();
        try {
            $ReturnEntry->status = $status === ReturnEntry::APPROVED ? ReturnEntry::APPROVED : ReturnEntry::REJECTED;
            $ReturnEntry->save();

            if ($status === ReturnEntry::APPROVED) {
                $ReturnEntry->order->client->increment('balance', $ReturnEntry->amount_refunded);

                $order = $ReturnEntry->order;
                $paymentMethods = $order->payment_methods ?? [];

                $paymentMethods[] = [
                    'amount' => (float) $ReturnEntry->amount_refunded,
                    'method' => 'cash_usd',
                    'currency' => 'USD',
                    'reference' => null,
                    'inputAmount' => null,
                    'debounceTimeout' => 0,
                    "isDebt" => true
                ];

                $order->payment_methods = $paymentMethods;
                $order->save();
            }
            DB::commit();
            Log::info("Devolución $status exitosamente.", ['returnEntry_id' => $ReturnEntry->id]);
            return $ReturnEntry;
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error("Error al $status la devolución: " . $e->getMessage(), [
                'returnEntry_id' => $ReturnEntry->id,
                'trace' => $e->getTraceAsString(),
            ]);
            throw $e;
        }
    }

    /**
     * Distribuye la cantidad devuelta en lotes (reingreso a inventario).
     * Solo para devoluciones ya aprobadas (uso posterior al aprobado).
     */
    public function distributeLots(ReturnEntry $returnEntry, array $updatedLots, array $newLots): void
    {
        if ($returnEntry->status !== ReturnEntry::APPROVED) {
            throw new Exception('Solo se puede distribuir lotes en devoluciones aprobadas.');
        }
        $this->applyLotDistribution($returnEntry, $updatedLots, $newLots);
    }

    /**
     * Aplica la distribución en lotes: stock actual + unidades devueltas.
     * Valida que la suma distribuida coincida con la cantidad devuelta.
     * updated_lots: [{ id, quantity }] cantidad final por lote existente.
     * new_lots: [{ lot_number, expiration_date, location, quantity }].
     * Retorna lista de [ product_lot_id, quantity ] para generar movimientos de inventario por lote (como en cyclics).
     */
    private function applyLotDistribution(ReturnEntry $returnEntry, array $updatedLots, array $newLots): array
    {
        $productId = $returnEntry->product_id;
        $returnQty = (int) $returnEntry->quantity;

        $totalDistributed = 0;
        $distributionForMovements = [];

        ProductLot::withoutEvents(function () use ($returnEntry, $updatedLots, $newLots, $productId, $returnQty, &$totalDistributed, &$distributionForMovements) {
            foreach ($updatedLots as $row) {
                $lot = ProductLot::where('id', $row['id'])->where('product_id', $productId)->first();
                if (!$lot) {
                    throw new Exception("Lote no encontrado o no pertenece al producto.");
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
            throw new Exception("La cantidad distribuida ({$totalDistributed}) debe coincidir con las unidades devueltas ({$returnQty}). Ajuste los lotes para que el total sea stock actual + devolución.");
        }

        return $distributionForMovements;
    }

    /**
     * Aprueba la devolución solo después de distribuir las unidades en lotes.
     * Flujo: 1) Distribuir en lotes (stock actual + devolución), 2) Registrar un movimiento de inventario (tipo devolución) por cada lote, como en cyclics, 3) Aprobar devolución (saldo, etc.).
     */
    public function approveWithDistribution(ReturnEntry $returnEntry, array $updatedLots, array $newLots): ReturnEntry
    {
        if ($returnEntry->status !== null) {
            throw new Exception('Solo se puede aprobar con distribución una devolución pendiente.');
        }

        DB::beginTransaction();
        try {
            $product = $returnEntry->product;
            $stockBefore = (int) ($product->stock ?? 0);

            $distributionForMovements = $this->applyLotDistribution($returnEntry, $updatedLots, $newLots);

            // Stock objetivo = suma real de lotes (actual en lotes + lo devuelto distribuido)
            $stockAfter = (int) $product->fresh()->lots()->sum('quantity');

            Product::withoutEvents(function () use ($product, $stockAfter) {
                $product->update(['stock' => $stockAfter]);
            });

            foreach ($distributionForMovements as $item) {
                InventoryMovement::create([
                    'product_id' => $returnEntry->product_id,
                    'product_lot_id' => $item['product_lot_id'],
                    'movement_type' => 'return',
                    'quantity' => (int) $item['quantity'],
                    'invoice_id' => null,
                    'supplier_id' => null,
                    'order_id' => $returnEntry->order_id,
                    'user_id' => Auth::id(),
                    'stock_before' => $stockBefore,
                    'stock_after' => $stockAfter,
                    'movement_date' => now(),
                ]);
            }

            $returnEntry = $this->updateStatus($returnEntry, ReturnEntry::APPROVED);
            DB::commit();
            return $returnEntry;
        } catch (\Throwable $e) {
            DB::rollBack();
            throw $e;
        }
    }
}
