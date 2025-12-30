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
use App\Models\ProductLot;

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
                $data['total_amount_usd'] = $data['total_amount_usd'] ?? 0;
                $data['money_returns'] = $data['money_returns'] ?? 0;
                $data['total_cost'] = $data['total_cost'] ?? 0;
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
            $availableStock = (int) $product->lots_sum_quantity ?? 0;

            $requestedQuantity = $validatedData['quantity'];
            $unitPriceAtOrder = $validatedData['price_at_product'];

            if ($order->currency === 'COP') {
                // Round up to nearest 100 COP
                $unitPriceAtOrder = ceil($unitPriceAtOrder / 100) * 100;
            }

            $price_usd = $validatedData['price_usd_unit'];
            $packId = $validatedData['pack_id'] ?? null;

            // HANDLE PACKS: Use standard logic (single line, no splitting)
            if ($packId) {
                // ... Existing Pack Logic ...
                $orderItem = $order->details()->where('product_id', $validatedData['product_id'])->where('pack_id', $packId)->first();

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
                    // Pack price handling (usually overridden by caller or pack logic, but keeping existing update logic)
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
                        'pack_id' => $packId,
                        'product_type' => 'pack',
                    ]);
                }

                DB::commit();
                $orderItem->load([
                    'product' => function ($q) {
                        $q->withSum('lots', 'quantity');
                    }
                ]);
                $orderItem->product->valid_stock_sum = $orderItem->product->lots_sum_quantity;
                return $orderItem;
            }

            // HANDLE NORMAL PRODUCTS: Split based on Expiration Rules

            // 1. Remove existing non-pack items for this product to re-calculate distribution
            $order->details()->where('product_id', $validatedData['product_id'])->whereNull('pack_id')->delete();

            if ($requestedQuantity === 0) {
                DB::commit();
                return new OrderDetail(['product_id' => $validatedData['product_id'], 'quantity' => 0]);
            }

            if ($requestedQuantity > $availableStock) {
                throw new InsufficientStockException($product->name, $availableStock, $requestedQuantity, 'Stock insuficiente.');
            }

            // 2. Fetch Rules and Lots
            $expirationOffers = \App\Models\ExpirationOffer::where('is_active', true)
                ->orderBy('months_to_expiration', 'asc') // "Less number of months first"
                ->get();

            $lots = $product->lots()->where('quantity', '>', 0)->orderBy('expiration_date', 'asc')->get();

            // 3. Distribute
            $remainingQty = $requestedQuantity;
            $buckets = []; // Key: 'rule_ID' (or 'normal'), Value: Quantity

            foreach ($lots as $lot) {
                if ($remainingQty <= 0)
                    break;

                $take = min($remainingQty, $lot->quantity);
                $remainingQty -= $take;

                $matchedRule = null;
                if ($lot->expiration_date && $expirationOffers->isNotEmpty()) {
                    $monthsToExpiry = Carbon::now()->floatDiffInMonths($lot->expiration_date, false);

                    // Logic: Rule applies if lot expires WITHIN X months. 
                    // Example: Exp in 2.5 months. Rule 3 months -> Matches (2.5 <= 3). 
                    // Rule 2 months -> No Match (2.5 > 2).
                    foreach ($expirationOffers as $offer) {
                        if ($monthsToExpiry <= $offer->months_to_expiration) {
                            $matchedRule = $offer;
                            break; // Pick the first one (sorted by ASC months, so "smallest months" rule)
                        }
                    }
                }

                $key = $matchedRule ? 'offer_' . $matchedRule->id : 'normal';
                if (!isset($buckets[$key])) {
                    $buckets[$key] = [
                        'qty' => 0,
                        'rule' => $matchedRule
                    ];
                }
                $buckets[$key]['qty'] += $take;
            }

            // If requested qty > sum of lots (should be covered by InsufficientStockException check earlier, but if lots drift...)
            // The availableStock check earlier used lots_sum_quantity, so strictly we are safe.
            // But if there is any gap, remainingQty might be > 0 if lots changed. 
            // We'll treat any remainder as 'normal' (standard stock pointer).
            if ($remainingQty > 0) {
                if (!isset($buckets['normal']))
                    $buckets['normal'] = ['qty' => 0, 'rule' => null];
                $buckets['normal']['qty'] += $remainingQty;
            }

            // 4. Create Lines
            $mainItem = null;

            foreach ($buckets as $key => $data) {
                $qty = $data['qty'];
                $rule = $data['rule'];

                if ($qty <= 0)
                    continue;

                $finalUnitPrice = $unitPriceAtOrder;
                $discountPct = 0;
                $discountType = null;
                $discountSource = null;

                if ($rule) {
                    $discountPct = $rule->discount_percentage;
                    $discountType = 'expiration';
                    $discountSource = $rule->id;
                    // Apply discount
                    $finalUnitPrice = ceil($unitPriceAtOrder * (1 - ($discountPct / 100)) / 100) * 100;
                }

                // Compute Total Price Explicitly (Unit * Qty)
                $calculatedTotalPrice = (float) ($finalUnitPrice * $qty);
                $calculatedUsdPrice = (float) ($price_usd * $qty);



                // If mixing currencies, price_usd handling might need adjustment but assuming base behavior

                $newItem = $order->details()->create([
                    'product_id' => $validatedData['product_id'],
                    'quantity' => $qty,
                    'price' => $calculatedTotalPrice, // Use explicit variable
                    'unit_cost' => $finalUnitPrice,     // Unit price for this line
                    'unit_price_usd' => $rule ? ($price_usd * (1 - ($discountPct / 100))) : $price_usd,

                    'pack_id' => null,
                    'product_type' => $rule ? 'offer' : 'normal',
                    'discount_percentage' => $discountPct > 0 ? $discountPct : null,
                    'discount_type' => $discountType,
                    'discount_source_id' => $discountSource,
                ]);

                if (!$mainItem || $qty > $mainItem->quantity) {
                    $mainItem = $newItem;
                }
            }

            DB::commit();
            if ($mainItem) {
                $mainItem->load([
                    'product' => function ($q) {
                        $q->withSum('lots', 'quantity');
                    }
                ]);
                $mainItem->product->valid_stock_sum = $mainItem->product->lots_sum_quantity;
                return $mainItem;
            }

            // Fallback if transaction commited but no items created (qty 0 case handled above)
            return new OrderDetail(['product_id' => $validatedData['product_id'], 'quantity' => 0]);


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
            $order->total_amount_usd = $validatedData['total_amount_usd'];
            $order->total_cost = $validatedData['total_cost'];
            $order->currency = $targetCurrency;
            $order->save();
            $order->load('details.product');
            foreach ($order->details as $item) {
                $product = $item->product;
                $priceToSet = 0;
                if ($item->pack_id && $item->unit_price_usd > 0) {
                    switch ($targetCurrency) {
                        case 'USD':
                            $priceToSet = $item->unit_price_usd;
                            break;
                        case 'BS':
                            $rate = ($product->sale_price > 0) ? ($product->price_bs / $product->sale_price) : 0;
                            // Fallback if rate calculation fails, though product should have prices
                            if ($rate == 0 && $product->price_bs > 0)
                                $rate = 1; // Unlikely but fail safe to avoid 0
                            $priceToSet = $item->unit_price_usd * $rate;
                            break;
                        case 'COP':
                            $rate = ($product->sale_price > 0) ? ($product->price_cop / $product->sale_price) : 0;
                            $priceToSet = $item->unit_price_usd * $rate;
                            break;
                        default:
                            $priceToSet = $item->unit_price_usd;
                    }
                } else {
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
                }

                // Preservar y re-aplicar descuento si existe
                if ($item->discount_percentage > 0 && $item->discount_type) {
                    $discountFactor = 1 - ($item->discount_percentage / 100);
                    $priceToSet = $priceToSet * $discountFactor;
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

    public function invoicing(Order $order, $spe)
    {

        $fiscalexist = FiscalHistory::where('order_id', $order->id)->first();
        $totalIva = 0;
        $exemptAmount = 0;
        $taxableAmount = 0;
        $client = $order->client;
        if (!$fiscalexist) {
            foreach ($order->details as $detail) {
                $product = $detail->product;
                $priceBs = $product->price_bs;
                $quantity = $detail->quantity;

                $itemSubtotal = $priceBs * $quantity;

                if ($product->iva == 1) {
                    $ivaRate = 0.16;
                    $itemTotal = $priceBs * $quantity;
                    $itemIva = $itemTotal * $ivaRate;
                    $totalIva += $itemIva;
                    $taxableAmount += $itemSubtotal;
                } else {
                    $exemptAmount += $itemSubtotal;
                }
            }

            $totalAmountBs = $exemptAmount + $taxableAmount + ($spe ? ($totalIva * 0.25) : $totalIva);

            $fiscalHistory = FiscalHistory::create([
                'user_id' => $order->seller_id,
                'order_id' => $order->id,
                'invoice_number' => null,
                'business_name' => $client->name . ' ' . $client->last_name,
                'identification' => $client->identification_type . $client->identification,
                'address' => $client->address,
                'exempt_amount' => $exemptAmount,
                'taxable_amount' => $taxableAmount,
                'iva_amount' => $totalIva,
                'total_amount' => $totalAmountBs,
                'invoice_date' => Carbon::now(),
                'spe' => $spe
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

            $generalSettings = DB::table('general_settings')->first();
            $isFiscalActive = $generalSettings && $generalSettings->fiscal_mode === 'activa';

            if ($request->hasFile('prescription_image')) {
                $path = $request->file('prescription_image')->store('recipe', 'public');
                $orderId->url_recipe = $path;
            }

            // Save discount details if provided
            if ($request->has('items')) {
                foreach ($request->items as $itemData) {
                    if (isset($itemData['order_detail_id'])) {
                        $detail = OrderDetail::where('id', $itemData['order_detail_id'])
                            ->where('order_id', $orderId->id)
                            ->first();

                        if ($detail) {
                            if (isset($itemData['quantity'])) {
                                $detail->quantity = $itemData['quantity'];
                            }

                            if ($orderId->currency === 'COP') {
                                $detail->price = ceil($itemData['price'] * $detail->quantity / 100) * 100;
                                $detail->unit_cost = ceil($itemData['price'] / 100) * 100;
                                $detail->price_before_discount = ceil($itemData['price_before_discount'] * $detail->quantity / 100) * 100; 
                            } else {
                                $detail->price = $itemData['price'] * $detail->quantity;
                                $detail->unit_cost = $itemData['price'];
                                $detail->price_before_discount = $itemData['price_before_discount'] * $detail->quantity;
                            }

                            if (isset($itemData['discount_percentage'])) {
                                $detail->discount_percentage = $itemData['discount_percentage'];
                                $detail->discount_type = $itemData['discount_type'] ?? null;
                                $detail->discount_source_id = $itemData['discount_source_id'] ?? null;
                            }
                            // Also update unit_price_usd if sent?
                            if (isset($itemData['price_usd'])) { // Assuming logic handles currency conversion elsewhere or passed here
                                // $detail->unit_price_usd = ...; 
                            }

                            $detail->save();
                        }
                    }
                }
            }

            if (isset($request->changeAmount)) {
                $orderId->money_returns = $request->changeAmount;
            }

            if (isset($request->changeAmountUSD)) {
                $orderId->usd_conversion = $request->changeAmountUSD;
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

            // First, update lot quantities BEFORE saving the order
            // This way, when the order is saved and triggers handleOrderMovement,
            // the sale movement will be created first, preventing expired movements
            $orderId->load('details.product.lots');

            foreach ($orderId->details as $detail) {
                $quantityToReduce = $detail->quantity;


                $quantityExpiration = 0;
                $lots = $detail->product->lots->sortBy('expiration_date');


                foreach ($lots as $lot) {
                    if ($quantityToReduce <= 0) {
                        break;
                    }

                    $taken = 0;
                    if ($lot->quantity >= $quantityToReduce) {
                        $taken = $quantityToReduce;
                        $lot->quantity -= $quantityToReduce;
                        ProductLot::withoutEvents(function () use ($lot) {
                            $lot->save();
                        });
                        $quantityToReduce = 0;
                    } else {
                        $taken = $lot->quantity;
                        $quantityToReduce -= $lot->quantity;
                        $lot->quantity = 0;
                        ProductLot::withoutEvents(function () use ($lot) {
                            $lot->save();
                        });
                    }


                    // Check if this lot is expiring (within 6 months)
                    if ($lot->expiration_date) {
                        $expDate = Carbon::parse($lot->expiration_date);
                        $sixMonthsLimit = Carbon::now()->addMonths(6);
                        if ($expDate->lt($sixMonthsLimit)) {
                            $quantityExpiration += $taken;
                        }
                    }
                }

                if ($quantityToReduce > 0) {
                    throw new \Exception("No hay suficiente stock en los lotes para el producto ID: {$detail->product->id}");
                }

                // Save quantity_expiration
                if ($quantityExpiration > 0) {
                    $detail->quantity_expiration = $quantityExpiration;
                    $detail->save();
                }
            }

            // Now save the order - this will trigger OrderObserver which calls handleOrderMovement
            // The sale movement will be created, and then when ProductLotObserver fires (if withoutEvents didn't work),
            // it will see the recent sale movement and skip creating expired/adjustment movements
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


            if ($isFiscalActive) {
                $this->invoicing($orderId, $request->spe);
                $ivaEjecuted = true;
            }else if ($request->generate_invoice) {
                $this->invoicing($orderId, $request->spe);
                $ivaEjecuted = true;
            }

        if (!$ivaEjecuted) {
            foreach ($orderId->details as $detail) {
                if ($detail->product) {
                    if (!$request->generate_invoice) {
                        if (($orderId->currency == "BS" || $detail->product->iva == 1) && !$ivaEjecuted) {
                            $this->invoicing($orderId, $request->spe);
                            $ivaEjecuted = true;
                        }
                    }
                }
            }
        }

            DB::table('order_details')->where('order_id', $orderId->id)->update(['updated_at' => Carbon::now()]);
            $current_cash = CashClosing::where('status', CashClosing::OPEN)->where('seller_id', $orderId->seller_id)->first();
            if (!isset($current_cash)) {
                $current_cash = CashClosing::create([
                    'seller_id' => $orderId->seller_id,
                    'status' => CashClosing::OPEN,
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
            } else {
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

            $cop_in_usd = $current_cash->total_cop_in_usd;
            $bs_in_usd = $current_cash->total_bs_in_usd;
            $current_cash->total_sales = $current_cash->total_usd + $current_cash->usd_credit + $cop_in_usd + $bs_in_usd;
            $current_cash->closing_date = Carbon::now();
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

    public function reserveOrder(Order $order, $sellerId): array
    {
        DB::beginTransaction();
        try {
            $order->status = Order::RESERVED;
            $order->save();
            $order->load('seller', 'client', 'details.product');

            DB::commit();
            Log::info("Orden reservada exitosamente.", ['order_id' => $order->id]);
            return [
                'reserved_order' => $order,
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

    public function reserveAndAddOrder(Order $order, $sellerId): array
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

    public function cancelledOrder(Order $order): Order
    {
        DB::beginTransaction();
        try {
            $order->status = Order::CANCELLED;
            $order->save();
            $order->load('details', 'cashClosing');

            foreach ($order->details as $item) {
                $productLot = ProductLot::where('product_id', $item->product_id)
                    ->where(function ($query) {
                        $query->whereNull('expiration_date')
                            ->orWhere('expiration_date', '>', Carbon::now());
                    })
                    ->orderBy('expiration_date', 'asc')
                    ->orderBy('id', 'asc')
                    ->first();

                if (!$productLot) {
                    Log::error("No se encontró un lote activo/válido para devolver el producto.", [
                        'order_item_id' => $item->id,
                        'product_id' => $item->product_id,
                        'order_id' => $order->id,
                    ]);
                    throw new \Exception("No se pudo devolver el inventario para el producto ID: {$item->product_id}. No hay lote disponible.");
                }
                $productLot->increment('quantity', $item->quantity);
            }
            $cashClosing = $order->cashClosing;
            if (!$cashClosing) {
                Log::warning("Orden ID {$order->id} no tiene un cierre de caja asociado para descontar montos.");
            } else {

                foreach ($order->payment_methods as $payment) {
                    $amount = $payment['amount'];
                    $method = $payment['method'];
                    switch ($method) {
                        case 'cash_usd':
                            if (isset($order->usd_conversion) && $order->usd_conversion > 0.0) {
                                $montoDesc = $amount - $order->usd_conversion;
                                $cashClosing->usd_cash -= $montoDesc;
                                $cashClosing->usd_conversion -= $order->usd_conversion ?? null;
                            } else {
                                $cashClosing->usd_cash -= $amount;
                            }
                            break;
                        case 'binance':
                            $cashClosing->usd_binance -= $amount;
                            break;
                        case 'paypal':
                            $cashClosing->usd_paypal -= $amount;
                            break;
                        case 'credit':
                            $cashClosing->usd_credit -= $order->total_amount;
                            break;
                        case 'cash_bs':
                            $cashClosing->bs_cash -= $amount;
                            break;
                        case 'mobile_payment':
                            $cashClosing->bs_mobile -= $amount;
                            break;
                        case 'bank_transfer_bs':
                            $cashClosing->bs_transfer -= $amount;
                            break;
                        case 'card':
                            $cashClosing->bs_card -= $amount;
                            break;
                        case 'cash_cop':
                            if (isset($order->usd_conversion) && $order->usd_conversion > 0.0) {
                                $cashClosing->cop_conversion -= $order->money_returns ?? null;
                            } else {
                                $montoDescCOP = $amount - $order->money_returns;
                                $cashClosing->cop_cash -= $montoDescCOP;
                            }
                            break;
                        case 'bank_transfer':
                            $cashClosing->cop_transfer -= $amount;
                            break;
                        case 'balance':
                            $cashClosing->usd_balance -= $amount;
                            break;
                    }
                }

                $total_bs = $cashClosing->bs_cash + $cashClosing->bs_mobile + $cashClosing->bs_transfer + $cashClosing->bs_card;
                $total_cop = ($cashClosing->cop_cash + $cashClosing->cop_transfer) - $cashClosing->cop_conversion;
                $total_usd = $cashClosing->usd_cash + $cashClosing->usd_binance + $cashClosing->usd_paypal + $cashClosing->usd_balance + $cashClosing->usd_conversion;

                $cashClosing->total_bs = $total_bs;
                $cashClosing->total_cop = $total_cop;
                $cashClosing->total_usd = $total_usd;
                $cashClosing->usd_delivered = $cashClosing->usd_cash + $cashClosing->usd_conversion;
                $cashClosing->cop_delivered = $cashClosing->cop_cash - $cashClosing->cop_conversion;
                $cashClosing->bs_delivered = $cashClosing->bs_cash;

                $cop_in_usd = $cashClosing->total_cop_in_usd;
                $bs_in_usd = $cashClosing->total_bs_in_usd;
                $cashClosing->total_sales = $cashClosing->total_usd + $cashClosing->usd_credit + $cop_in_usd + $bs_in_usd;

                $cashClosing->update();
            }

            DB::commit();
            Log::info("Orden cancelada exitosamente.", ['order_id' => $order->id]);
            return $order;
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('Error al cancelada la orden: ' . $e->getMessage(), [
                'order_id' => $order->id,
                'trace' => $e->getTraceAsString(),
            ]);
            throw $e;
        }
    }
}
