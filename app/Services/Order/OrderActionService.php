<?php

namespace App\Services\Order;

use App\Models\Order;
use App\Models\OrderDetail;
use App\Models\Product;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use App\Models\CashClosing;
use Exception;
use App\Exceptions\InsufficientStockException;

class OrderActionService
{
    public function createOrder(array $data): Order
    {
        DB::beginTransaction();
        try {

            $openCashRegisterClosing = CashClosing::where('seller_id', $data['seller_id'])
                ->where('status', CashClosing::OPEN) // <-- Asume que tienes una constante OPEN
                ->first();
            if (!$openCashRegisterClosing) {
                throw new Exception('No se encontró un cierre de caja abierto para el vendedor.');
            } else {
                $data['cash_closing_id'] = $openCashRegisterClosing->id;
                $data['total_amount'] = $data['total_amount'] ?? 0;
                $data['money_returns'] = $data['money_returns'] ?? 0;
                $data['payment_methods'] = null;
            }

            $order = Order::create($data);

            DB::commit();
            return $order;
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error al crear la orden: ' . $e->getMessage());
            throw $e;
        }
    }

    public function getMyOpenOrder(int $sellerId): ?Order
    {
        try {
            $openOrder = Order::where('seller_id', $sellerId)
                ->where('status','Pending')
                ->with([
                    'client',
                    'details' => function ($query) {
                        $query->with([
                            'product' => function ($q) {
                                $q->with('laboratory')
                                    ->withSum('lots', 'quantity');
                            }
                        ]);
                    }
                ])
                ->first();
            return $openOrder;
        } catch (\Exception $e) {
              Log::error('Error en getMyOpenOrder para seller_id: ' . $sellerId . ' - ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
                'exception' => $e
            ]);
            throw $e;
        }
    }


       public function addUpdateOrderItems(Order $order, array $validatedData): OrderDetail
    {
        DB::beginTransaction();
        try {
             
            $product = Product::findOrFail($validatedData['product_id']);
            $product->loadSum('lots', 'quantity');
            $availableStock = (int)$product->lots_sum_quantity ?? 0;
        
            $requestedQuantity = $validatedData['quantity'];
            $unitPriceAtOrder = $validatedData['price_at_product'];
              
            $orderItem = $order->details()->where('product_id', $validatedData['product_id'])->first();

            if ($requestedQuantity === 0) {
                if ($orderItem) {
                    $orderItem->delete();
                    DB::commit();
                    $orderItem->quantity = 0;
                    return $orderItem;
                }
                DB::commit();
                return new OrderDetail(['product_id' => $validatedData['product_id'], 'quantity' => 0]);
            }

             if ($requestedQuantity > $availableStock) {
                throw new InsufficientStockException(
                    $product->name,
                    $availableStock,
                    $requestedQuantity,
                    'Stock insuficiente para la cantidad solicitada.'
                );
            }

            if ($orderItem) {

                $orderItem->quantity = $requestedQuantity;
                $orderItem->price = $unitPriceAtOrder * $requestedQuantity;
                $orderItem->unit_cost = $unitPriceAtOrder;
                $orderItem->save();
            } else {
                $orderItem = $order->details()->create([
                    'product_id' => $validatedData['product_id'],
                    'quantity' => $requestedQuantity,
                    'price' => $unitPriceAtOrder * $requestedQuantity,
                    'unit_cost' => $unitPriceAtOrder,
                ]);
            }
            DB::commit();
            $orderItem->load([
                'product' => function ($q) {
                    $q->with('laboratory')
                      ->withSum('lots', 'quantity');
                }
            ]);
            $orderItem->product->valid_stock_sum = $orderItem->product->lots_sum_quantity;
            return $orderItem;

        } catch (InsufficientStockException $e) {
            DB::rollBack();
            Log::warning("Intento de agregar o actualizar productos con stock insuficiente: " . $e->getMessage(), [
                'order_id' => $order->id,
                'product_id' => $validatedData['product_id'],
                'requested_quantity' => $validatedData['quantity'],
                'available_stock' => $e->getAvailableStock(),
            ]);
            throw $e;
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('Error al agregar o actualizar ítems de la orden: ' . $e->getMessage(), [
                'order_id' => $order->id,
                'validated_data' => $validatedData,
                'trace' => $e->getTraceAsString(),
            ]);
            throw $e;
        }
    }
    public function updateordenCurrency(Order $order, array $validatedData): Order
    {
        DB::beginTransaction();
        try {
            $targetCurrency = $validatedData['currency'];
            $order->total_amount = $validatedData['total_amount'];
            $order->currency = $targetCurrency;
            $order->save();
            $order->load('details.product');
            foreach ($order->details as $item) {
                $product = $item->product;
                $priceToSet = 0;
                switch ($targetCurrency) {
                    case 'USD':
                        $priceToSet = $product->sale_price ?? $product->price ?? 0;
                        break;
                    case 'BS':
                        $priceToSet = $product->price_bs ?? 0;
                        break;
                    case 'COP':
                        $priceToSet = $product->price_cop ?? 0;
                        break;
                    default:
                        $priceToSet = $product->sale_price ?? $product->price ?? 0;
                        break;
                }

                $item->price = $priceToSet * $item->quantity;
                $item->unit_cost = $priceToSet;
                $item->save();
            }
            DB::commit();
            return $order;
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    public function deleteDetail(Order $order, OrderDetail $orderDetail): bool
    {
        DB::beginTransaction();
        try {
            if ($orderDetail->order_id !== $order->id) {
                throw new \InvalidArgumentException("Order detail does not belong to the specified order.");
            }

            $orderDetail->delete();
            DB::commit();
            return true;
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('Error al eliminar el producto de la orden: ' . $e->getMessage(), [
                'order_detail_id' => $orderDetail->id,
                'trace' => $e->getTraceAsString(),
            ]);
            throw $e;
        }
    }

    public function abandonOrder(Order $order): Order
    {
        DB::beginTransaction();
         try {
            $order->status = 'abandoned';
            $order->save();
            DB::commit();
            Log::info("Orden abandonada exitosamente.", ['order_id' => $order->id]);
            return $order;
         }catch (\Throwable $e) {
            DB::rollBack();
            Log::error('Error al abandonar la orden: ' . $e->getMessage(), [
                'order_id' => $order->id,
                'trace' => $e->getTraceAsString(),
            ]);
            throw $e;
        }
    }
}
