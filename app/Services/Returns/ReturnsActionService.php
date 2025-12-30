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

            $query = Order::where('created_at', '>=', Carbon::now()->subHours(48))
                ->where('status', Order::COMPLETED)
                ->with('client', 'details.product')
                ->where(function ($query) use ($searchTerm) {
                    $query->where('id', $searchTerm);
                    $query->orWhereHas('client', function ($q) use ($searchTerm) {
                        $q->where('identification', $searchTerm);
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
                $orderBy = $options['orderBy'] ?? 'asc';
                $query->orderBy($sortBy, $orderBy);
            }

            return $query;
        } catch (\Exception $e) {
            DB::rollBack();
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

            $returnAmount = round(($returnsQuantity * (float) $orderDetail->unit_price_usd) * (100 - (float) $orderDetail->discount_percentage) / 100, 2);
            $clientData = $orderData['client'];

            if (!$clientData) {
                throw new Exception('No se encontró el cliente asociado a la orden.');
            }

            if (!$productLotId) {
                throw new Exception('Debe seleccionar un lote para la devolución.');
            }

            // Validar que el lote existe y pertenece al producto
            $lot = ProductLot::where('id', $productLotId)
                ->where('product_id', $productData['id'])
                ->first();

            if (!$lot) {
                throw new Exception('El lote seleccionado no existe o no pertenece al producto.');
            }

            // Incrementar la cantidad del lote seleccionado
            $lot->quantity += $returnsQuantity;
            ProductLot::withoutEvents(function () use ($lot) {
                $lot->save();
            });


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
}
