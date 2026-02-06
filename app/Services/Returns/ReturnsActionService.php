<?php

namespace App\Services\Returns;

use App\Services\Resources\ResourceService;
use App\Models\Client;
use App\Models\Order;
use App\Models\ProductLot;
use App\Models\ReturnEntry;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Exception;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use App\Observers\ProductObserver;

class ReturnsActionService
{
    public function __construct(private ResourceService $resourceService)
    {
    }


    public function searchOrdersReturns(string $searchTerm, array $options): Builder
    {
        try {
            $searchTerm = trim($searchTerm);
            if ($searchTerm === '') {
                return Order::query()->whereRaw('1 = 0');
            }

            // Órdenes de los últimos 7 días (una semana) para devoluciones (por fecha de orden)
            $query = Order::where('order_date', '>=', Carbon::now()->subDays(7)->startOfDay())
                ->where('status', Order::COMPLETED)
                ->with('client', 'details.product')
                ->where(function ($q) use ($searchTerm) {
                    // Búsqueda por ID de orden (solo si es un número corto que podría ser ID)
                    if (is_numeric($searchTerm) && strlen($searchTerm) <= 8) {
                        $q->where('orders.id', (int) $searchTerm);
                    }
                    // Búsqueda por cédula/identificación del cliente (ej: V-24150980, 24150980)
                    $q->orWhereHas('client', function ($sub) use ($searchTerm) {
                        $sub->where('identification', $searchTerm)
                            ->orWhere('identification', 'like', "%{$searchTerm}%")
                            ->orWhereRaw('CONCAT(COALESCE(identification_type,""), COALESCE(identification,"")) LIKE ?', ["%{$searchTerm}%"]);
                    });
                    // Búsqueda por nombre del cliente
                    $q->orWhereHas('client', function ($sub) use ($searchTerm) {
                        $sub->where('name', 'like', "%{$searchTerm}%")
                            ->orWhere('last_name', 'like', "%{$searchTerm}%")
                            ->orWhereRaw('CONCAT(COALESCE(name,""), " ", COALESCE(last_name,"")) LIKE ?', ["%{$searchTerm}%"]);
                    });
                });

            $hasReturn = (clone $query);
            if ($hasReturn->count() == 1) {
                if ($hasReturn->whereHas('returns')->exists()) {
                    throw new Exception('Esta orden ya tiene una devolución registrada y no puede ser modificada.');
                }
            }

            $query->whereDoesntHave('returns');

            if (isset($options['sortBy']) && !empty($options['sortBy'])) {
                $sortBy = $options['sortBy'];
                $orderBy = $options['orderBy'] ?? 'desc';
                $query->orderBy($sortBy, $orderBy);
            } else {
                $query->orderBy('order_date', 'desc');
            }

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

            // Si se proporciona lote, validar e incrementar cantidad
            if ($productLotId) {
                $lot = ProductLot::where('id', $productLotId)
                    ->where('product_id', $productData['id'])
                    ->first();

                if (!$lot) {
                    throw new Exception('El lote seleccionado no existe o no pertenece al producto.');
                }

                $lot->quantity += $returnsQuantity;
                ProductLot::withoutEvents(function () use ($lot) {
                    $lot->save();
                });
            }

            // Siempre se crea en estado pendiente (null). La aprobación y asignación
            // del monto al saldo del cliente se hace en /tpv/returnsSupervisor
            $return = ReturnEntry::create([
                'order_id' => $orderData['id'],
                'generated_by_id' => Auth::id(),
                'product_id' => $productData['id'],
                'quantity' => $returnsQuantity,
                'amount_refunded' => $returnAmount,
                'return_date' => Carbon::now(),
                'status' => null
            ]);

            ProductObserver::handleReturnMovement($return);
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
     */
    private function applyLotDistribution(ReturnEntry $returnEntry, array $updatedLots, array $newLots): void
    {
        $productId = $returnEntry->product_id;
        $returnQty = (int) $returnEntry->quantity;

        $totalDistributed = 0;

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
            }
        }

        foreach ($newLots as $row) {
            $qty = (int) ($row['quantity'] ?? 0);
            if ($qty <= 0) {
                continue;
            }
            ProductLot::create([
                'product_id' => $productId,
                'lot_number' => $row['lot_number'] ?? '',
                'expiration_date' => !empty($row['expiration_date']) ? $row['expiration_date'] : null,
                'location' => $row['location'] ?? '',
                'quantity' => $qty,
            ]);
            $totalDistributed += $qty;
        }

        if ($totalDistributed !== $returnQty) {
            throw new Exception("La cantidad distribuida ({$totalDistributed}) debe coincidir con las unidades devueltas ({$returnQty}). Ajuste los lotes para que el total sea stock actual + devolución.");
        }
    }

    /**
     * Aprueba la devolución solo después de distribuir las unidades en lotes.
     * Flujo: 1) Distribuir en lotes (stock actual + devolución), 2) Aprobar devolución (saldo, etc.).
     */
    public function approveWithDistribution(ReturnEntry $returnEntry, array $updatedLots, array $newLots): ReturnEntry
    {
        if ($returnEntry->status !== null) {
            throw new Exception('Solo se puede aprobar con distribución una devolución pendiente.');
        }

        DB::beginTransaction();
        try {
            $this->applyLotDistribution($returnEntry, $updatedLots, $newLots);
            $returnEntry = $this->updateStatus($returnEntry, ReturnEntry::APPROVED);
            DB::commit();
            return $returnEntry;
        } catch (\Throwable $e) {
            DB::rollBack();
            throw $e;
        }
    }
}
