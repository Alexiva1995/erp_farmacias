<?php

namespace App\Services\Order;

use App\Models\Order;
use App\Models\OrderDetail;
use App\Models\Product;
use App\Models\Credit;
use App\Models\Client;
use App\Models\FiscalHistory;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use App\Models\CashClosing;
use Exception;
use App\Exceptions\InsufficientStockException;
use Illuminate\Http\Request;
use Carbon\Carbon;

class OrderActionService
{
    public function createOrder(array $data): Order
    {
        DB::beginTransaction();
        try {

            $openCashRegisterClosing = CashClosing::where('seller_id', $data['seller_id'])
                ->where('status', CashClosing::OPEN)
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
            $order->load('seller', 'client');
            return $order;
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error al crear la orden: ' . $e->getMessage());
            throw $e;
        }
    }

    public function getMyOpenOrder(int $sellerId): array
    {
        try {

            $withRelations = [
            'client',
            'seller',
            'details' => function ($query) {
                $query->with([
                    'product' => function ($q) {
                        $q->with('laboratory')
                          ->withSum('lots', 'quantity');
                    }
                ]);
            }
        ];

          $openOrder = Order::where('seller_id', $sellerId)
            ->where('status', Order::PENDING)
            ->with($withRelations)
            ->first();

            $reservedOrder = Order::where('seller_id', $sellerId)
            ->where('status', 'Reserved')
            ->with($withRelations)
            ->first();
        
             return [
            'pending_order' => $openOrder,
            'reserved_order' => $reservedOrder
            ];
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
            $price_usd = $validatedData['price_usd_unit'];

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
                $orderItem->unit_price_usd = $price_usd;
                $orderItem->save();
            } else {
                $orderItem = $order->details()->create([
                    'product_id' => $validatedData['product_id'],
                    'quantity' => $requestedQuantity,
                    'price' => $unitPriceAtOrder * $requestedQuantity,
                    'unit_cost' => $unitPriceAtOrder,
                    'unit_price_usd' => $price_usd,
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
            $order->status = Order::ABANDONED;
            $order->save();
            DB::commit();
            Log::info("Orden abandonada exitosamente.", ['order_id' => $order->id]);
            return $order;
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('Error al abandonar la orden: ' . $e->getMessage(), [
                'order_id' => $order->id,
                'trace' => $e->getTraceAsString(),
            ]);
            throw $e;
        }
    }

    public function invoicing(Order $order)
    {

        $fiscalexist = FiscalHistory::where('order_id', $order->id)->first();
        $totalIva = 0;
        $exemptAmount = 0;
        $client = $order->client;
        if (!$fiscalexist) {
            foreach ($order->details as $detail) {
                $product = $detail->product;
                $priceBs = $product->price_bs;
                $quantity = $detail->quantity;

                if ($product->iva == 1) {
                    $ivaRate = 0.16;
                    $itemTotal = $priceBs * $quantity;
                    $itemIva = $itemTotal * $ivaRate;
                    $totalIva += $itemIva;
                }

                $exemptAmount += $priceBs * $quantity;
            }

            $totalAmountBs = $exemptAmount + $totalIva;
            $fiscalHistory = FiscalHistory::create([
                'user_id'      => $order->seller_id,
                'order_id'       => $order->id,
                'invoice_number' => null,
                'business_name' => $client->name . ' ' . $client->last_name,
                'identification' => $client->identification_type . $client->identification,
                'address' => $client->address,
                'exempt_amount'     => $exemptAmount,
                'iva_amount'     => $totalIva,
                'total_amount'   => $totalAmountBs,
                'invoice_date'   => Carbon::now(),
            ]);

            $fiscalHistory->save();
            return $fiscalHistory;
        }
        return $fiscalexist;
    }

    public function complete(Order $orderId, Request $request, $sellerId): array
    {
        DB::beginTransaction();
        try {
            $orderId->status = Order::COMPLETED;
            $orderId->payment_methods = $request->payments;
            $ivaEjecuted = false;

            if (isset($request->changeAmount)) {
                $orderId->money_returns = $request->changeAmount;
            }


            $currencies = [];
            foreach ($request->payments as $payment) {
                $method = $payment['method'];
                if ($payment['currency'] == 'USD') {
                    $currencies[] = 'USD';
                } elseif ($payment['currency'] == 'BS') {
                    $currencies[] = 'BS';
                } elseif ($payment['currency'] == 'COP') {
                    $currencies[] = 'COP';
                }
                if ($method === 'binance' || $method === 'paypal' || $method === 'credit') {
                    $currencies[] = 'USD';
                }
            }

            $uniqueCurrencies = array_unique($currencies);
            $orderId->has_multiple_currencies = (count($uniqueCurrencies) > 1) ? 1 : 0;

            $orderId->save();

            $balancePayment = collect($request->payments)->firstWhere('method', 'balance');
            if ($balancePayment) {
                $client = $orderId->client;
                $client->balance -= $balancePayment['amount'];
                $client->save();
            }

            if ($request->credit) {
                Credit::create([
                    'client_id' => $request->client_id,
                    'order_id' => $orderId->id,
                    'credit_amount' => $request->total_amount,
                    'pending_amount' => $request->total_amount,
                    'credit_date' => Carbon::now(),
                    'status' => 'Active'
                ]);
            }

            $orderId->load('details.product.lots');

            foreach ($orderId->details as $detail) {
                $quantityToReduce = $detail->quantity;
                $lots = $detail->product->lots->sortBy('expiration_date');
                foreach ($lots as $lot) {
                    if ($quantityToReduce <= 0) {
                        break;
                    }
                    if ($lot->quantity >= $quantityToReduce) {
                        $lot->quantity -= $quantityToReduce;
                        $lot->save();
                        $quantityToReduce = 0;
                    } else {
                        $quantityToReduce -= $lot->quantity;
                        $lot->quantity = 0;
                        $lot->save();
                    }
                }
                if ($quantityToReduce > 0) {
                    throw new \Exception("No hay suficiente stock en los lotes para el producto ID: {$detail->product->id}");
                }
            }


            if ($request->generate_invoice) {
                $this->invoicing($orderId);
                $ivaEjecuted = true;
            }

            foreach ($orderId->details as $detail) {
                if ($detail->product) {
                    if (!$request->generate_invoice) {
                        if (($orderId->currency == "BS" || $detail->product->iva == 1) && !$ivaEjecuted) {
                            $this->invoicing($orderId);
                            $ivaEjecuted = true;
                        }
                    }
                }
            }

            DB::table('order_details')->where('order_id', $orderId->id)->update(['updated_at' => Carbon::now()]);
            $current_cash = CashClosing::where('status', CashClosing::OPEN)->where('seller_id', $orderId->seller_id)->first();
            if (!isset($current_cash)) {
                $current_cash = CashClosing::create([
                    'seller_id' => $orderId->seller_id,
                    'status' =>  CashClosing::OPEN,
                    'closing_date' => Carbon::now(),
                ]);
            }

            foreach ($request->payments as $payment) {
                $method = $payment['method'] ?? null;
                $amount = $payment['amount'] ?? 0;

                if (isset($method)) {
                    switch ($method) {
                        case 'cash_usd':
                            $current_cash->usd_cash += $amount;
                            break;
                        case 'binance':
                            $current_cash->usd_binance += $amount;
                            break;
                        case 'paypal':
                            $current_cash->usd_paypal += $amount;
                            break;
                        case 'credit':
                            $current_cash->usd_credit += $request->total_amount;
                            break;
                        case 'cash_bs':
                            $current_cash->bs_cash += $amount;
                            break;
                        case 'mobile_payment':
                            $current_cash->bs_mobile += $amount;
                            break;
                        case 'bank_transfer_bs':
                            $current_cash->bs_transfer += $amount;
                            break;
                        case 'card':
                            $current_cash->bs_card += $amount;
                            break;
                        case 'cash_cop':
                            $current_cash->cop_cash += $amount;
                            break;
                        case 'bank_transfer':
                            $current_cash->cop_transfer += $amount;
                            break;
                        case 'balance':
                            $current_cash->usd_balance += $amount;
                            break;
                    }
                }
            }

           
            if (isset($request->changeAmountUSD) && $request->changeAmountUSD > 0) {
                $current_cash->usd_cash -= $request->changeAmountUSD;
                $current_cash->cop_conversion += $request->changeAmount ?? null;
                $current_cash->usd_conversion += $request->changeAmountUSD ?? null;
            }else{
                if (isset($request->changeAmount)) {
                    $current_cash->cop_cash -= $request->changeAmount;
                }
            }
      
            $total_bs = $current_cash->bs_cash + $current_cash->bs_mobile + $current_cash->bs_transfer + $current_cash->bs_card;
            $total_cop = ($current_cash->cop_cash + $current_cash->cop_transfer) - $current_cash->cop_conversion;
            $total_usd = $current_cash->usd_cash + $current_cash->usd_binance + $current_cash->usd_paypal + $current_cash->usd_balance + $current_cash->usd_conversion;

            $current_cash->total_bs = $total_bs;
            $current_cash->total_cop = $total_cop;
            $current_cash->total_usd = $total_usd;
            $current_cash->usd_delivered = $current_cash->usd_cash + $current_cash->usd_conversion;
            $current_cash->cop_delivered = $current_cash->cop_cash - $current_cash->cop_conversion;
            $current_cash->bs_delivered = $current_cash->bs_cash;
            $current_cash->update();


            $reservedOrder = Order::where('seller_id', $sellerId)
                ->where('status', Order::RESERVED)
                ->first();

            
            $newPendingOrder = null;

            if ($reservedOrder) {
                $reservedOrder->status = Order::PENDING;
                $reservedOrder->save();
                $reservedOrder->load('seller', 'client', 'details.product');
                $newPendingOrder = $reservedOrder;
            }


            DB::commit();
            return [
                'order' => $newPendingOrder,
            ];

        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('Error al completar la orden: ' . $e->getMessage(), [
                'order_id' => $orderId->id,
                'trace' => $e->getTraceAsString(),
            ]);
            throw $e;
        }
    }

    public function reserveOrder(Order $order,$sellerId): array
    {
          DB::beginTransaction();
        try {
            $order->status = Order::RESERVED;
            $order->save();
            /*$openCashRegisterClosing = CashClosing::where('seller_id', $sellerId)
                ->where('status', CashClosing::OPEN)
                ->first();

            if (!$openCashRegisterClosing) {
                throw new Exception('No se encontró un cierre de caja abierto para el vendedor.');
            } 

                $data['client_id'] = $order->client_id;
                $data['seller_id'] = $sellerId;
                $data['cash_closing_id'] = $openCashRegisterClosing->id;
                $data['total_amount'] =  0;
                $data['money_returns'] =  0;
                $data['payment_methods'] = null;

            $newOrder = Order::create($data);
            $newOrder->load('seller', 'client', 'details.product');*/
            $order->load('seller', 'client', 'details.product');

            DB::commit();
            Log::info("Orden reservada exitosamente.", ['order_id' => $order->id]);
             return [
            'reserved_order' => $order,
            //'pending_order' => $newOrder
            ];
        } catch (\Throwable $e) {

            DB::rollBack();
            Log::error('Error al reservar la orden: ' . $e->getMessage(), [
                'order_id' => $order->id,
                'trace' => $e->getTraceAsString(),
            ]);
            throw $e;
        }
    }

     public function reserveAndAddOrder(Order $order,$sellerId): array
    {
        DB::beginTransaction();
        try {

            $orderOpen = Order::where('seller_id', $sellerId)
                ->where('status', Order::PENDING)
                ->first();

            if (!$orderOpen) {
                throw new \Exception("No hay una orden abierta para este vendedor.");
            }

            $orderOpen->status = Order::RESERVED;
            $order->status = Order::PENDING;

            $orderOpen->save(); 
            $order->save();

            $orderOpen->load('seller', 'client', 'details.product');
            $order->load('seller', 'client', 'details.product');

            DB::commit();
            Log::info("Orden agregada exitosamente.", ['order_id' => $order->id]);
            return [
            'reserved_order' => $orderOpen,
            'pending_order' => $order,
            ];
        } catch (\Throwable $e) {

            DB::rollBack();
            Log::error('Error al agregar la orden: ' . $e->getMessage(), [
                'order_id' => $order->id,
                'trace' => $e->getTraceAsString(),
            ]);
            throw $e;
        }
    }
}
