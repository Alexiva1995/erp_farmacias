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

    public function productReturn(Request $request)
    {
        DB::beginTransaction();

        try {
            $orderData = $request->order;
            $productData = $request->product;
            $orderDetail = collect($orderData['details'])->firstWhere('product_id', $productData['id']);
            $returnsQuantity = (int) $request->input('returns_quantity');

            if ($returnsQuantity <= 0) {
                throw new Exception('La cantidad a devolver debe ser mayor a cero.');
            }

            if (!$orderDetail) {
                throw new Exception('No se encontró el detalle del producto en la orden.');
            }
            $orderDetail = (object) $orderDetail;

            $returnAmount = $returnsQuantity * (float) $orderDetail->unit_price_usd;
            $clientData = $orderData['client'];

            if (!$clientData) {
                throw new Exception('No se encontró el cliente asociado a la orden.');
            }

            $lot = ProductLot::where('product_id', $productData['id'])
                ->where('expiration_date', '>', now())
                ->where('quantity', '>', 0)
                ->orderByDesc('expiration_date')
                ->first();

            if ($lot) {
                $lot->quantity += $returnsQuantity;
                ProductLot::withoutEvents(function () use ($lot) {
                    $lot->save();
                });
            } else {
                throw new Exception('No se encontró lote vigente para ese producto.');
            }


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
