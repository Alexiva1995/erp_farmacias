<?php

namespace App\Services\Order;

use App\Models\Order;
use App\Models\OrderDetail;
use App\Models\Product;
use App\Models\Credit;
use App\Models\FiscalHistory;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use App\Models\CashClosing;
use Exception;
use App\Exceptions\InsufficientStockException;
use Illuminate\Http\Request;
use Carbon\Carbon;
use App\Models\ProductLot;
use App\Services\Resources\ResourceService;
use App\Models\IndividualOffer;
use App\Models\CategoryOffer;
use App\Models\FiscalHistoryDetail;

class OrderActionService
{
    public function createOrder(array $data): Order
    {
        // DB::beginTransaction();
        try {
            return DB::transaction(function () use ($data) {
                $pendingOrder = Order::where('seller_id', $data['seller_id'])
                    ->where('status', Order::PENDING)
                    ->withCount('details')
                    ->first();

                if ($pendingOrder && $pendingOrder->details_count > 0) {
                    throw new \Exception('Ya tienes una orden abierta con productos. Procesa esa orden o resérvala antes de crear una nueva.');
                }

                // Si hay una orden pendiente pero está vacía, la eliminamos antes de crear la nueva
                if ($pendingOrder) {
                    $pendingOrder->delete();
                }

                $openCashRegisterClosing = CashClosing::where('seller_id', $data['seller_id'])
                    ->where('status', CashClosing::OPEN)
                    ->first();

                if (!$openCashRegisterClosing) {
                    throw new \Exception('No se encontró un cierre de caja abierto para el vendedor.');
                }

                $data['cash_closing_id'] = $openCashRegisterClosing->id;
                $data['total_amount'] = $data['total_amount'] ?? 0;
                $data['total_amount_usd'] = $data['total_amount_usd'] ?? 0;
                $data['money_returns'] = $data['money_returns'] ?? 0;
                $data['total_cost'] = $data['total_cost'] ?? 0;
                $data['payment_methods'] = null;

                $order = Order::create($data);
                $order->load('seller', 'client');
                Log::info("Orden {$order->id} creada por vendedor {$data['seller_id']}");
                return $order;
            });

            /*  $openCashRegisterClosing = CashClosing::where('seller_id', $data['seller_id'])
                ->where('status', CashClosing::OPEN)
                ->first();*/

            /* if (!$openCashRegisterClosing) {
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
            return $order;*/
        } catch (\Exception $e) {
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
                ->where('status', Order::RESERVED)
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
            Log::info('--- ADD_ORDER_ITEM START ---');
            Log::info('Product details:', [
                'id' => $product->id,
                'name' => $product->name,
                'sale_price' => $product->sale_price,
                'price_cop_attribute' => $product->price_cop, // Test if attribute is working here
            ]);
            Log::info('Payload received:', $validatedData);
            Log::info('Order info:', ['id' => $order->id, 'currency' => $order->currency]);

            $product->loadSum('lots', 'quantity');
            $availableStock = (int) $product->lots_sum_quantity ?? 0;

            $requestedQuantity = $validatedData['quantity'];
            $unitPriceAtOrder = $validatedData['price_at_product'];

            if ($order->currency === 'COP') {
                $roundedBefore = $unitPriceAtOrder;
                $unitPriceAtOrder = ceil($unitPriceAtOrder / 100) * 100;
                Log::info('COP Rounding Applied:', [
                    'original' => $roundedBefore,
                    'rounded' => $unitPriceAtOrder
                ]);
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
                    $orderItem->unit_cost = $product->unit_cost; // Costo real, no precio de venta
                    $orderItem->unit_price_usd = $price_usd;
                    $orderItem->save();
                } else {
                    $orderItem = $order->details()->create([
                        'product_id' => $validatedData['product_id'],
                        'quantity' => $requestedQuantity,
                        'price' => $unitPriceAtOrder * $requestedQuantity,
                        'unit_cost' => $product->unit_cost, // Costo real, no precio de venta
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



            // 1. Remove existing non-pack items for this product
            $order->details()->where('product_id', $validatedData['product_id'])->whereNull('pack_id')->delete();

            if ($requestedQuantity === 0) {
                DB::commit();
                return new OrderDetail(['product_id' => $validatedData['product_id'], 'quantity' => 0]);
            }

            if ($requestedQuantity > $availableStock) {
                throw new InsufficientStockException($product->name, $availableStock, $requestedQuantity, 'Stock insuficiente.');
            }

            // --- FETCH OFFERS (Individual & Category) ---
            $now = Carbon::now();

            // Individual Offer
            $individualOffer = IndividualOffer::where('product_id', $product->id)
                ->where('start_date', '<=', $now)
                ->where('end_date', '>=', $now)
                ->orderBy('discount_percent', 'desc')
                ->first();

            // Category Offer
            $categoryOffer = null;
            if ($product->category_id) {
                $categoryOffer = CategoryOffer::where('category_id', $product->category_id)
                    ->where('is_active', true)
                    ->where('start_date', '<=', $now)
                    ->where('end_date', '>=', $now)
                    ->orderBy('discount_percentage', 'desc')
                    ->first();
            }

            // Determine Best Base Discount (Individual vs Category)
            $baseDiscountPct = 0;
            $baseDiscountType = null;
            $baseDiscountSource = null;

            if ($individualOffer) {
                $baseDiscountPct = $individualOffer->discount_percent;
                $baseDiscountType = 'individual';
                $baseDiscountSource = $individualOffer->id;
            }

            if ($categoryOffer && $categoryOffer->discount_percentage > $baseDiscountPct) {
                $baseDiscountPct = $categoryOffer->discount_percentage;
                $baseDiscountType = 'category';
                $baseDiscountSource = $categoryOffer->id;
            }

            // 2. Fetch Rules and Lots
            $expirationOffers = \App\Models\ExpirationOffer::where('is_active', true)
                ->orderBy('months_to_expiration', 'asc')
                ->get();

            $lots = $product->lots()->where('quantity', '>', 0)->orderBy('expiration_date', 'asc')->get();

            // 3. Distribute
            $remainingQty = $requestedQuantity;
            $buckets = [];

            foreach ($lots as $lot) {
                if ($remainingQty <= 0)
                    break;

                $take = min($remainingQty, $lot->quantity);
                $remainingQty -= $take;

                $matchedRule = null;
                if ($lot->expiration_date && $expirationOffers->isNotEmpty()) {
                    $monthsToExpiry = Carbon::now()->floatDiffInMonths($lot->expiration_date, false);
                    foreach ($expirationOffers as $offer) {
                        if ($monthsToExpiry <= $offer->months_to_expiration) {
                            $matchedRule = $offer;
                            break;
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

                // Start with Base Discount
                $discountPct = $baseDiscountPct;
                $discountType = $baseDiscountType;
                $discountSource = $baseDiscountSource;

                // If Expiration Rule exists, compare and take Max
                if ($rule) {
                    if ($rule->discount_percentage > $discountPct) {
                        $discountPct = $rule->discount_percentage;
                        $discountType = 'expiration';
                        $discountSource = $rule->id;
                    }
                }

                // Apply logic
                if ($discountPct > 0) {
                    $finalUnitPrice = $unitPriceAtOrder * (1 - ($discountPct / 100));
                    if ($order->currency === 'COP') {
                        $finalUnitPrice = ceil($finalUnitPrice / 100) * 100;
                    }
                }

                // Compute Total Price Explicitly (Unit * Qty)
                $calculatedTotalPrice = (float) ($finalUnitPrice * $qty);

                $newItem = $order->details()->create([
                    'product_id' => $validatedData['product_id'],
                    'quantity' => $qty,
                    'price' => $calculatedTotalPrice,
                    'unit_cost' => $product->unit_cost, // Costo real del producto (siempre en moneda base USD de costos)
                    'unit_price_usd' => $discountPct > 0 ? ($price_usd * (1 - ($discountPct / 100))) : $price_usd,
                    'pack_id' => null,
                    'product_type' => $rule ? 'offer' : 'normal', // Keep 'offer' if expiration involved, or maybe 'discounted'? Leaving as is for now.
                    'discount_percentage' => $discountPct > 0 ? $discountPct : null,
                    'discount_type' => $discountType,
                    'discount_source_id' => $discountSource,
                ]);

                if (!$mainItem || $qty > $mainItem->quantity) {
                    $mainItem = $newItem;
                }
            }


            $order->updateTotals();

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
            // $order->total_cost = $validatedData['total_cost']; // No usar costo del frontend
            $order->currency = $targetCurrency;
            //   dd($targetCurrency);
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
                            $salePrice = $product->sale_price ?? 0;
                            $priceBs = $product->price_bs ?? 0;
                            $rate = ($salePrice > 0) ? ($priceBs / $salePrice) : 0;
                            // Fallback if rate calculation fails, though product should have prices
                            if ($rate == 0 && $priceBs > 0)
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
                // $item->unit_cost = $priceToSet; // NO sobreescribir el costo real con el precio de venta recalculado
                $item->save();
            }
            $order->updateTotals(); // Recalcular costos y totales correctamente en el servidor
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
        $taxable_base = 0;
        $client = $order->client;

        $resourceService = app(ResourceService::class);
        $exchangeRate = $resourceService->getExchangeRate('BS');

        if (!$fiscalexist) {
            foreach ($order->details as $detail) {
                $product = $detail->product;
                //$priceBs = $product->price_bs;
                $quantity = $detail->quantity;
                $unitPriceInOrderCurrency = $quantity > 0 ? ($detail->price / $quantity) : 0;
                $unitPriceInUsd = $detail->unit_price_usd;

                $priceBs = (strtoupper($order->currency) !== 'BS')
                    ? ($unitPriceInUsd * $exchangeRate)
                    : $unitPriceInOrderCurrency;

                // Si el producto tiene IVA, extraer el neto (el precio en la orden ya incluye IVA)
                if ($product->iva == 1) {
                    $priceBs = $priceBs / 1.16;
                }

                $itemSubtotal = $priceBs * $quantity;

                if ($product->iva == 1) {
                    $ivaRate = 0.16;
                    $itemIva = $itemSubtotal * $ivaRate;
                    $totalIva += $itemIva;
                    $taxableAmount += $itemSubtotal;
                } else {
                    $exemptAmount += $itemSubtotal;
                }
            }

            // --- CÁLCULOS Recarga Sujeto pasivo especial 3%---
            $taxable_base = $exemptAmount + $taxableAmount + ($spe ? ($totalIva * 0.25) : $totalIva);

            $speRate = $order->spe_surcharge_rate ?? 0;
            $speAmountBs = ($speRate > 0) ? ($taxable_base * ($speRate / 100)) : 0;

            $totalAmountBs = $taxable_base + $speAmountBs;

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
                'spe_surcharge_rate' => $speRate,
                'spe_surcharge_amount' => $speAmountBs,
                'exchange_rate' => $exchangeRate,
                'invoice_date' => Carbon::now(),
                'spe' => $spe
            ]);

            $fiscalHistory->save();

            foreach ($order->details as $detail) {
                $unitPriceInOrderCurrency = $detail->unit_cost;

                $product = $detail->product;
                //$priceBs = $product->price_bs;

                $unitPriceInOrderCurrency = $detail->quantity > 0 ? ($detail->price / $detail->quantity) : 0;
                $unitPriceInUsd = $detail->unit_price_usd;

                $priceBs = (strtoupper($order->currency) !== 'BS')
                    ? ($unitPriceInUsd * $exchangeRate)
                    : $unitPriceInOrderCurrency;

                // Extraer el neto para el desglose detallado si tiene IVA
                if ($product->iva == 1) {
                    $priceBs = $priceBs / 1.16;
                }

                $quantity = $detail->quantity;
                $isTaxable = ($product->iva == 1);
                $subtotal = $priceBs * $detail->quantity;
                $ivaAmount = $isTaxable ? ($subtotal * 0.16) : 0;
                $totalItem = $subtotal + $ivaAmount;


                // Insertamos en la tabla de detalles
                FiscalHistoryDetail::create([
                    'fiscal_history_id' => $fiscalHistory->id,
                    'product_id' => $product->id,
                    'product_name' => $product->name,
                    'quantity' => $quantity,
                    'vat_status' => $isTaxable ? 1 : 0,
                    'exempt_amount' => !$isTaxable ? $subtotal : 0,
                    'iva_amount' => $ivaAmount,
                    'total_amount' => $totalItem,
                    'big_amount' => $totalItem,
                ]);
            }


            return $fiscalHistory;
        }
        return $fiscalexist;
    }

    /**
     * Valida que la suma de los pagos cubra el total de la orden (en la moneda de la orden).
     * Lanza \InvalidArgumentException si no coinciden o la suma es menor.
     */
    private function validatePaymentsCoverOrderTotal(Order $order, array $payments): void
    {
        $resourceService = app(ResourceService::class);
        $orderCurrency = strtoupper($order->currency ?? 'USD');
        $orderTotal = (float) $order->total_amount;
        $tolerance = 0.02; // Tolerancia para redondeos (2 centavos)

        $rates = [
            'USD' => $resourceService->getExchangeRate('USD') ?: 1,
            'COP' => $resourceService->getExchangeRate('COP') ?: 1,
            'BS' => $resourceService->getExchangeRate('BS') ?: 1,
        ];
        $orderRate = $rates[$orderCurrency] ?? 1;



        $sumInOrderCurrency = 0;
        foreach ($payments as $p) {
            $amount = (float) ($p['amount'] ?? 0);
            $currency = strtoupper($p['currency'] ?? 'USD');
            $rate = $rates[$currency] ?? 1;
            // Convertir a moneda de la orden: amount en X -> USD -> orden
            // $amountInOrderCurrency = $rate > 0 ? ($amount / $rate) * $orderRate : 0;
            // $sumInOrderCurrency += $amountInOrderCurrency;
            if ($currency === $orderCurrency) {
                $sumInOrderCurrency += $amount;
            } else {
                $amountInBase = ($currency === 'USD') ? $amount : ($amount / $rate);
                $sumInOrderCurrency += $amountInBase * $orderRate;
            }

        }

        if ($sumInOrderCurrency < ($orderTotal - $tolerance)) {
            throw new \InvalidArgumentException(
                'La suma de los pagos (' . round($sumInOrderCurrency, 2) . ') no cubre el total de la orden (' . round($orderTotal, 2) . ' ' . $orderCurrency . ').'
            );
        }
    }

    public function complete(Order $orderId, Request $request, $sellerId): array
    {
        DB::beginTransaction();
        try {
            // 1. Idempotencia: si la orden ya está completada, retornar sin reprocesar
            $order = Order::where('id', $orderId->id)->lockForUpdate()->firstOrFail();
            if ($order->status === Order::COMPLETED || $order->status === 'paid') {
                DB::commit();
                $order->load(['seller', 'client', 'details.product']);
                return [
                    'orderCompletada' => $order,
                    'already_completed' => true,
                ];
            }

            $orderId = $order;
            $orderId->status = Order::COMPLETED;
            $orderId->payment_methods = $request->payments;
            $ivaEjecuted = false;

            $generalSettings = DB::table('general_settings')->first();
            $isFiscalActive = $generalSettings && $generalSettings->fiscal_mode === 'activa';

            if ($request->hasFile('prescription_image')) {
                $path = $request->file('prescription_image')->store('recipe', 'public');
                $orderId->url_recipe = $path;
            }

            // Save discount details if provided (optimizado: cargar detalles una vez)
            if ($request->has('items') && !empty($request->items)) {
                $detailsById = $orderId->details()->get()->keyBy('id');

                foreach ($request->items as $itemData) {
                    $detailId = $itemData['order_detail_id'] ?? null;
                    $detail = $detailId ? $detailsById->get($detailId) : null;
                    if (!$detail) {
                        continue;
                    }
                    if (isset($itemData['quantity'])) {
                        $detail->quantity = $itemData['quantity'];
                    }
                    if ($orderId->currency === 'COP') {
                        $detail->price = ceil(($itemData['price'] ?? 0) * $detail->quantity / 100) * 100;
                        // $detail->unit_cost = ceil(($itemData['unit_cost'] ?? 0) / 100) * 100; // No sobreescribir costo real
                        $detail->price_before_discount = ceil(($itemData['price_before_discount'] ?? 0) * $detail->quantity / 100) * 100;
                    } else {
                        $detail->price = ($itemData['price'] ?? 0) * $detail->quantity;
                        // $detail->unit_cost = $itemData['unit_cost'] ?? 0; // No sobreescribir costo real
                        $detail->price_before_discount = ($itemData['price_before_discount'] ?? 0) * $detail->quantity;
                    }
                    if (isset($itemData['discount_percentage'])) {
                        $detail->discount_percentage = $itemData['discount_percentage'];
                        $detail->discount_type = $itemData['discount_type'] ?? null;
                        $detail->discount_source_id = $itemData['discount_source_id'] ?? null;
                    }
                    $detail->save();
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

            // Bloqueo anti-overselling: cargar y bloquear los lotes de los productos de la orden
            $orderId->load('details.product');
            $productIds = $orderId->details->pluck('product_id')->unique()->filter()->values()->all();
            $lotsByProduct = collect();
            if (!empty($productIds)) {
                $lockedLots = ProductLot::whereIn('product_id', $productIds)
                    ->where('quantity', '>', 0)
                    ->orderBy('expiration_date')
                    ->lockForUpdate()
                    ->get();
                $lotsByProduct = $lockedLots->groupBy('product_id');
            }

            foreach ($orderId->details as $detail) {
                $quantityToReduce = (int) $detail->quantity;
                $quantityExpiration = 0;
                $lots = $lotsByProduct->get($detail->product_id, collect())->sortBy('expiration_date')->values();

                foreach ($lots as $lot) {
                    if ($quantityToReduce <= 0) {
                        break;
                    }

                    $taken = 0;
                    //  dd($lot->quantity >= $quantityToReduce);
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
                    $product = $detail->product;
                    $productName = $product?->name ?? 'Producto';
                    $available = $product ? (int) $product->lots->sum('quantity') : 0;
                    throw new InsufficientStockException(
                        $productName,
                        $available,
                        (int) $detail->quantity,
                        "No hay suficiente stock para '{$productName}'. Disponible: {$available}, solicitado: {$detail->quantity}."
                    );
                }

                // Save quantity_expiration
                if ($quantityExpiration > 0) {
                    $detail->quantity_expiration = $quantityExpiration;
                    $detail->save();
                }
            }

            // Recalcular totales desde los detalles en BD (no confiar en el cliente)
            $orderId->updateTotals();
            $orderId->total_amount_usd = $orderId->details->sum(function ($d) {
                return ($d->unit_price_usd ?? 0) * ($d->quantity ?? 0);
            });

            // Validación de integridad financiera: suma de pagos debe cubrir el total
            $this->validatePaymentsCoverOrderTotal($orderId, $request->payments);

            // Recargo Sujeto Pasivo Especial
            $orderId->taxable_base = $request->taxable_base ?? 0;
            $orderId->spe_surcharge_rate = $request->spe_surcharge_rate ?? 0;
            $orderId->spe_surcharge_amount = $request->spe_surcharge_amount ?? 0;
            $orderId->order_date = Carbon::now();

            // Now save the order - this will trigger OrderObserver which calls handleOrderMovement
            // The sale movement will be created, and then when ProductLotObserver fires (if withoutEvents didn't work),
            // it will see the recent sale movement and skip creating expired/adjustment movements
            $orderId->save();

            $balancePayment = collect($request->payments)->firstWhere('method', 'balance');
            if ($balancePayment) {
                $client = $orderId->client;
                $client->balance -= (float) ($balancePayment['amount'] ?? 0);
                $client->save();
            }

            if ($request->credit) {
                Credit::create([
                    'client_id' => $request->client_id,
                    'order_id' => $orderId->id,
                    'credit_amount' => $orderId->total_amount,
                    'pending_amount' => $orderId->total_amount,
                    'credit_date' => Carbon::now(),
                    'status' => 'Active'
                ]);
            }


            /*if ($isFiscalActive) {
                $this->invoicing($orderId, $request->spe);
                $ivaEjecuted = true;
            } else if ($request->generate_invoice) {
                $this->invoicing($orderId, $request->spe);
                $ivaEjecuted = true;
            }*/

            // 1. Verificar condiciones globales y de solicitud de fiscal
            $currency = strtoupper($orderId->currency);
            $shouldInvoice = $isFiscalActive || $request->generate_invoice || ($currency === 'BS');

            if (!$shouldInvoice) {
                $shouldInvoice = $orderId->details->contains(function ($detail) {
                    return optional($detail->product)->iva == 1;
                });
            }

            if ($shouldInvoice) {
                $this->invoicing($orderId, $request->spe);
                $ivaEjecuted = true;
            }

            /*if (!$ivaEjecuted) {
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
            }*/

            DB::table('order_details')->where('order_id', $orderId->id)->update(['updated_at' => Carbon::now()]);
            $current_cash = CashClosing::where('status', CashClosing::OPEN)->where('seller_id', $orderId->seller_id)->first();
            if (!$current_cash) {
                throw new \Exception('No se encontró un cierre de caja abierto para el vendedor. Debe abrir caja antes de completar la venta.');
            }

            // Usar montos validados: el total de la orden (calculado en servidor) para crédito;
            // los montos por método se validaron en validatePaymentsCoverOrderTotal
            $orderTotal = (float) $orderId->total_amount;

            foreach ($request->payments as $payment) {
                $method = $payment['method'] ?? null;
                $amount = (float) ($payment['amount'] ?? 0);

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
                            $current_cash->usd_credit += $orderTotal;
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
                        case 'debit_card':
                            $current_cash->bs_card_debito += $amount;
                            break;
                        case 'credit_card':
                            $current_cash->bs_card_credit += $amount;
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
                // Caso cambio moneda cruzada
                $current_cash->usd_cash -= $request->changeAmountUSD;
                $current_cash->cop_conversion += $request->changeAmount ?? null;
                $current_cash->usd_conversion += $request->changeAmountUSD ?? null;
            } else {
                // Caso misma moneda
                if (isset($request->changeAmount)) {
                    $current_cash->cop_cash -= $request->changeAmount;
                }
            }

            // Recalcular todos los totales usando la lógica unificada en el modelo
            $current_cash->closing_date = Carbon::now();
            $current_cash->recalculateTotals();


            /*$reservedOrder = Order::where('seller_id', $sellerId)
                ->where('status', Order::RESERVED)
                ->first();*/


            $newPendingOrder = null;

            /*if ($reservedOrder) {
                $reservedOrder->status = Order::PENDING;
                $reservedOrder->save();
                $reservedOrder->load('seller', 'client', 'details.product');
                $newPendingOrder = $reservedOrder;
            }*/

            $orderId->load(['seller', 'client', 'details.product']);
            DB::commit();
            return [
                //'order' => $newPendingOrder,
                'orderCompletada' => $orderId,
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

            $alreadyReserved = Order::where('seller_id', $sellerId)
                ->where('status', Order::RESERVED)
                ->where('id', '!=', $order->id)
                ->lockForUpdate()
                ->exists();

            if ($alreadyReserved) {
                throw new \Exception("Ya tienes una orden reservada. No puedes tener dos al mismo tiempo.");
            }

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
        try {
            return DB::transaction(function () use ($order, $sellerId) {
                $previouslyReserved = Order::where('seller_id', $sellerId)
                    ->where('status', Order::RESERVED)
                    ->where('id', '!=', $order->id)
                    ->lockForUpdate()
                    ->first();

                if ($previouslyReserved) {
                    $previouslyReserved->status = Order::PENDING;
                    $previouslyReserved->save();
                }

                $order->status = Order::RESERVED;
                $order->seller_id = $sellerId;
                $order->save();
                $order->load('seller', 'client', 'details.product');
                if ($previouslyReserved) {
                    $previouslyReserved->load('seller', 'client', 'details.product');
                }

                Log::info("Orden reservada exitosamente.", ['order_id' => $order->id]);

                return [
                    'reserved_order' => $order,
                    'pending_order' => $previouslyReserved,
                ];
            });
        } catch (\Exception $e) {
            Log::error("Error en reserveAndAddOrder: " . $e->getMessage());
            throw $e;
        }
    }

    /*  public function reserveAndAddOrder(Order $order, $sellerId): array
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
    }*/

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
                                $cashClosing->cop_conversion -= $order->money_returns ?? null;
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
                        case 'debit_card':
                            $cashClosing->bs_card_debito -= $amount;
                            break;
                        case 'credit_card':
                            $cashClosing->bs_card_credit -= $amount;
                            break;
                        case 'cash_cop':
                            /*if (isset($order->usd_conversion) && $order->usd_conversion > 0.0) {
                                Log::info("dentro de conversion.", $order->usd_conversion);
                                $cashClosing->cop_conversion -= $order->money_returns ?? null;
                            } else {
                                Log::info("dentro del else conversion.", $order->money_returns);*/
                            $montoDescCOP = $amount - $order->money_returns;
                            $cashClosing->cop_cash -= $montoDescCOP;
                            // }
                            break;
                        case 'bank_transfer':
                            $cashClosing->cop_transfer -= $amount;
                            break;
                        case 'balance':
                            $cashClosing->usd_balance -= $amount;
                            break;
                    }
                }

                $total_bs = $cashClosing->bs_cash + $cashClosing->bs_mobile + $cashClosing->bs_transfer + $cashClosing->bs_card_debito + $cashClosing->bs_card_credit;
                $total_cop = ($cashClosing->cop_cash + $cashClosing->cop_transfer) - $cashClosing->cop_conversion;
                $total_usd = $cashClosing->usd_cash + $cashClosing->usd_binance + $cashClosing->usd_paypal + $cashClosing->usd_balance + $cashClosing->usd_conversion;

                $cashClosing->total_bs = $total_bs;
                $cashClosing->total_cop = $total_cop;
                $cashClosing->total_usd = $total_usd;
                $cashClosing->usd_delivered = $cashClosing->usd_cash + $cashClosing->usd_conversion;
                $cashClosing->cop_delivered = $cashClosing->cop_cash - ($cashClosing->cop_conversion + $cashClosing->cop_conversion_payment_credit);
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
