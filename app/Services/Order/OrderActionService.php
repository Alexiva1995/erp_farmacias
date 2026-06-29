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
                    $openCashRegisterClosing = CashClosing::create([
                        'seller_id' => $data['seller_id'],
                        'status' => CashClosing::OPEN,
                        'opening_date' => Carbon::now(),
                    ]);
                    Log::info("Caja auto-abierta para el vendedor {$data['seller_id']} al intentar ingresar orden.");
                }

                $data['cash_closing_id'] = $openCashRegisterClosing->id;
                $data['total_amount'] = $data['total_amount'] ?? 0;
                $data['total_amount_usd'] = $data['total_amount_usd'] ?? 0;
                $data['money_returns'] = $data['money_returns'] ?? 0;
                $data['total_cost'] = $data['total_cost'] ?? 0;
                $data['payment_methods'] = null;
                $data['currency'] = $data['currency'] ?? 'USD';

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
                        },
                        // Cargar relación de plato para ítems de menú de restaurante
                        'dish' => function ($q) {
                            $q->with('category');
                        },
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
            // HANDLE DISHES (Only for restaurant mode)
            $dishId = $validatedData['dish_id'] ?? null;
            if ($dishId) {
                $dish = \App\Models\Dish::with('ingredients')->findOrFail($dishId);
                $requestedQuantity = (int)$validatedData['quantity'];
                
                // Derivar precio de designated_price
                $price_usd = $dish->designated_price ?? 0;
                $targetCurrency = $order->currency;
                if ($targetCurrency === 'USD') {
                    $unitPriceAtOrder = $price_usd;
                } elseif ($targetCurrency === 'COP') {
                    $resourceService = app(\App\Services\Resources\ResourceService::class);
                    $tasaCop = $resourceService->getExchangeRate('COP') ?: 1;
                    $unitPriceAtOrder = ceil(($price_usd * $tasaCop) / 100) * 100;
                } elseif ($targetCurrency === 'BS') {
                    $resourceService = app(\App\Services\Resources\ResourceService::class);
                    $tasaBs = $resourceService->getExchangeRate('BS') ?: 1;
                    $unitPriceAtOrder = round($price_usd * $tasaBs, 2);
                } else {
                    $unitPriceAtOrder = $price_usd;
                }

                // 1. Remove existing detail for this dish
                $order->details()->where('dish_id', $dishId)->delete();

                if ($requestedQuantity === 0) {
                    $order->updateTotals();
                    DB::commit();
                    return new OrderDetail(['dish_id' => $dishId, 'quantity' => 0]);
                }


                // 3. Create detail
                $orderItem = $order->details()->create([
                    'dish_id' => $dishId,
                    'quantity' => $requestedQuantity,
                    'price' => $unitPriceAtOrder,
                    'unit_cost' => $dish->cost_price ?? 0,
                    'unit_price_usd' => $price_usd,
                    'product_type' => 'dish',
                    'notes' => $validatedData['notes'] ?? null,
                ]);

                $order->updateTotals();
                DB::commit();
                // Recargar desde BD para obtener los campos de descuento actualizados por applyGeneralPromotions
                $orderItem = $orderItem->fresh(['dish' => function ($q) {
                    $q->with('category');
                }]);
                return $orderItem;
            }

            // HANDLE COURTS (Only for sports_rental mode)
            $courtId = $validatedData['court_id'] ?? null;
            if ($courtId) {
                $court = \App\Models\Court::findOrFail($courtId);
                $requestedQuantity = (float)$validatedData['quantity'];
                
                $price_usd = (float)$validatedData['price_usd_unit'];
                $unitPriceAtOrder = (float)$validatedData['price_at_product'];

                // 1. Remove existing detail for this court
                $order->details()->where('court_id', $courtId)->delete();

                if ($requestedQuantity <= 0) {
                    $order->updateTotals();
                    DB::commit();
                    return new OrderDetail(['court_id' => $courtId, 'quantity' => 0]);
                }

                // 2. Create detail
                $orderItem = $order->details()->create([
                    'court_id' => $courtId,
                    'quantity' => $requestedQuantity,
                    'price' => $unitPriceAtOrder,
                    'unit_cost' => 0,
                    'unit_price_usd' => $price_usd,
                    'product_type' => 'court',
                    'notes' => $validatedData['notes'] ?? null,
                ]);

                $order->updateTotals();
                DB::commit();
                return $orderItem->fresh(['court']);
            }

            $product = Product::findOrFail($validatedData['product_id']);
            $product->loadSum('lots', 'quantity');
            $availableStock = (int) $product->lots_sum_quantity ?? 0;

            $requestedQuantity = $validatedData['quantity'];
            $unitPriceAtOrder = $validatedData['price_at_product'];

            if ($order->currency === 'COP') {
                $unitPriceAtOrder = $unitPriceAtOrder; // Ya no redondeamos por unidad
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
                    // Pack price handling (unit price)
                    $orderItem->price = $unitPriceAtOrder;
                    $orderItem->unit_cost = $product->unit_cost; 
                    $orderItem->unit_price_usd = $price_usd;
                    $orderItem->save();
                } else {
                    $orderItem = $order->details()->create([
                        'product_id' => $validatedData['product_id'],
                        'quantity' => $requestedQuantity,
                        'price' => $unitPriceAtOrder,
                        'unit_cost' => $product->unit_cost, 
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
                    $now = now();
                    $diffMonths = ($lot->expiration_date->year - $now->year) * 12 + $lot->expiration_date->month - $now->month + 1;
                    $monthsToExpiry = max(1, $diffMonths);
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
                }

                // Compute Unit Price Explicitly
                $calculatedUnitPrice = (float) $finalUnitPrice;

                $newItem = $order->details()->create([
                    'product_id' => $validatedData['product_id'],
                    'quantity' => $qty,
                    'price' => $calculatedUnitPrice,
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
            return new OrderDetail([
                'product_id' => $validatedData['product_id'] ?? null,
                'dish_id' => $validatedData['dish_id'] ?? null,
                'quantity' => 0
            ]);
        } catch (InsufficientStockException $e) {
            DB::rollBack();
            Log::warning("Intento de agregar o actualizar productos con stock insuficiente: " . $e->getMessage(), [
                'order_id' => $order->id,
                'product_id' => $validatedData['product_id'] ?? null,
                'dish_id' => $validatedData['dish_id'] ?? null,
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
            $order->save();
            $order->load('details.product');

            // Instanciamos dinámicamente la estrategia de precios utilizando el Factory
            $pricingStrategy = \App\Services\Order\Strategies\PricingStrategyFactory::make($targetCurrency);

            foreach ($order->details as $item) {
                if ($item->dish_id) {
                    $usdPrice = $item->unit_price_usd;
                    if ($targetCurrency === 'USD') {
                        $priceToSet = $usdPrice;
                    } elseif ($targetCurrency === 'COP') {
                        $resourceService = app(\App\Services\Resources\ResourceService::class);
                        $tasaCop = $resourceService->getExchangeRate('COP') ?: 1;
                        $rawCop = $usdPrice * $tasaCop;
                        $priceToSet = ceil($rawCop / 100) * 100;
                    } elseif ($targetCurrency === 'BS') {
                        $resourceService = app(\App\Services\Resources\ResourceService::class);
                        $tasaBs = $resourceService->getExchangeRate('BS') ?: 1;
                        $priceToSet = round($usdPrice * $tasaBs, 2);
                    } else {
                        $priceToSet = $usdPrice;
                    }
                } else {
                    $product = $item->product;
                    
                    // Aplicamos el Patrón Estrategia para obtener el precio base de venta según divisa
                    $priceToSet = $pricingStrategy->calculatePrice($product, $item);
                }

                // Preservar y re-aplicar descuento si existe
                if ($item->discount_percentage > 0 && $item->discount_type) {
                    $discountFactor = 1 - ($item->discount_percentage / 100);
                    $priceToSet = $priceToSet * $discountFactor;
                }

                $item->price = $priceToSet;
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
        // Determinar si aplica SPE basándonos en la orden procesada
        $applySpe = ($order->spe_surcharge_rate > 0);
        $spe = $applySpe ? 1 : 0;

        $fiscalexist = FiscalHistory::where('order_id', $order->id)->first();
        $totalIva = 0;
        $exemptAmount = 0;
        $taxableAmount = 0;
        $taxable_base = 0;
        $client = $order->client;

        $resourceService = app(ResourceService::class);
        $exchangeRate = $resourceService->getExchangeRate('BS');
        $currencyOfOrder = strtoupper($order->currency ?? 'BS');
        $rateOfOrder = $resourceService->getExchangeRate($currencyOfOrder) ?: 1;

        if (!$fiscalexist) {
            $detailsForHash = [];
            foreach ($order->details as $detail) {
                // Los platos de restaurante no tienen producto asociado (product es null)
                if ($detail->product_type === 'dish') {
                    $dish = $detail->dish;
                    $priceBs = $detail->price_bs ?? (float)$detail->price;
                    $quantity = $detail->quantity;
                    $itemSubtotal = $priceBs * $quantity;
                    $exemptAmount += $itemSubtotal;
                    $detailsForHash[] = "dish_{$detail->dish_id}:{$quantity}:" . number_format($priceBs, 4, '.', '');
                    continue;
                }

                $product = $detail->product;
                $quantity = $detail->quantity;

                // Usar el precio en BS mandado desde el frontend (Fijo y exacto)
                // Si por alguna razón no existe (ventas viejas), cae de nuevo al catálogo
                $priceBs = $detail->price_bs ?? ($product->price_bs * (1 - (($detail->discount_percentage ?? 0) / 100)));

                // Si el producto tiene IVA, extraer el neto
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

                // Colectar detalles para el hash: product_id:qty:price_bs_unit
                $detailsForHash[] = "{$product->id}:{$quantity}:" . number_format($priceBs, 4, '.', '');
            }

            // --- CÁLCULOS FISCALES (Uniformidad total BS/COP/USD) ---
            $taxable_base = $exemptAmount + $taxableAmount + $totalIva;
            
            // Si aplica SPE, aplicamos el recargo del 1%
            $speRate = $applySpe ? 1.00 : 0.00;
            $speAmountBs = $applySpe ? ($taxable_base * 0.01) : 0.00;

            $totalAmountBs = $taxable_base + $speAmountBs;

            // --- GENERACIÓN DE HASH DE AUDITORÍA ---
            $clientIdentification = $client?->identification ?? '00000000';
            $clientBusinessName = $client ? trim($client->name . ' ' . $client->last_name) : 'CLIENTE GENERICO';
            $clientIdentificationFull = $client ? ($client->identification_type . $client->identification) : 'V00000000';
            $clientAddress = $client?->address ?? 'N/A';

            $auditString = implode('|', [
                $clientIdentification,
                number_format($exemptAmount, 2, '.', ''),
                number_format($taxableAmount, 2, '.', ''),
                number_format($totalIva, 2, '.', ''),
                number_format($totalAmountBs, 2, '.', ''),
                $order->id,
                implode('|', $detailsForHash)
            ]);
            $auditHash = hash('sha256', $auditString);

            $fiscalHistory = FiscalHistory::create([
                'user_id' => $order->seller_id,
                'order_id' => $order->id,
                'invoice_number' => null,
                'business_name' => $clientBusinessName,
                'identification' => $clientIdentificationFull,
                'address' => $clientAddress,
                'exempt_amount' => $exemptAmount,
                'taxable_amount' => $taxableAmount,
                'iva_amount' => $totalIva,
                'total_amount' => $totalAmountBs,
                'spe_surcharge_rate' => $speRate,
                'spe_surcharge_amount' => $speAmountBs,
                'exchange_rate' => $exchangeRate,
                'invoice_date' => Carbon::now(),
                'spe' => $spe,
                'is_queued' => true,
                'audit_hash' => $auditHash,
            ]);

            foreach ($order->details as $detail) {
                $unitPriceInOrderCurrency = $detail->unit_cost;
                $quantity = $detail->quantity;

                // Los platos de restaurante no tienen producto, tratar como exentos
                if ($detail->product_type === 'dish') {
                    $dish = $detail->dish;
                    $priceBs = $detail->price_bs ?? (float)$detail->price;
                    FiscalHistoryDetail::create([
                        'fiscal_history_id' => $fiscalHistory->id,
                        'product_id'        => null,
                        'product_name'      => $dish?->name ?? 'Plato',
                        'quantity'          => $quantity,
                        'vat_status'        => 0,
                        'exempt_amount'     => $priceBs,
                        'iva_amount'        => 0,
                        'total_amount'      => $priceBs,
                        'big_amount'        => $priceBs,
                    ]);
                    continue;
                }

                // Las canchas no tienen producto, tratar como exentas
                if ($detail->product_type === 'court') {
                    $court = $detail->court;
                    $priceBs = $detail->price_bs ?? (float)$detail->price;
                    FiscalHistoryDetail::create([
                        'fiscal_history_id' => $fiscalHistory->id,
                        'product_id'        => null,
                        'product_name'      => 'Alquiler: ' . ($court?->name ?? 'Cancha'),
                        'quantity'          => $quantity,
                        'vat_status'        => 0,
                        'exempt_amount'     => $priceBs,
                        'iva_amount'        => 0,
                        'total_amount'      => $priceBs,
                        'big_amount'        => $priceBs,
                    ]);
                    continue;
                }

                $product = $detail->product;

                // Usar el precio en BS mandado desde el frontend para el desglose
                $priceBs = $detail->price_bs ?? ($product->price_bs * (1 - (($detail->discount_percentage ?? 0) / 100)));

                // Extraer el neto para el desglose detallado si tiene IVA
                if ($product->iva == 1) {
                    $priceBs = $priceBs / 1.16;
                }

                $isTaxable = ($product->iva == 1);
                $ivaAmountUnit = $isTaxable ? ($priceBs * 0.16) : 0;
                $totalItemUnit = $priceBs + $ivaAmountUnit;

                // Insertamos en la tabla de detalles
                FiscalHistoryDetail::create([
                    'fiscal_history_id' => $fiscalHistory->id,
                    'product_id'        => $product->id,
                    'product_name'      => $product->name,
                    'quantity'          => $quantity,
                    'vat_status'        => $isTaxable ? 1 : 0,
                    'exempt_amount'     => !$isTaxable ? $priceBs : 0,
                    'iva_amount'        => $ivaAmountUnit,
                    'total_amount'      => $totalItemUnit,
                    'big_amount'        => $totalItemUnit,
                ]);
            }


            return $fiscalHistory;
        }
        return $fiscalexist;
    }

    /**
     * Valida que la suma de los pagos cubra el total de la orden (en la moneda de la orden).
     * Lanza \App\Exceptions\PaymentDiscrepancyException si no coinciden o la suma es menor.
     */
    private function validatePaymentsCoverOrderTotal(Order $order, array $payments, float $moneyReturns): void
    {
        $resourceService = app(ResourceService::class);
        $orderCurrency = strtoupper($order->currency ?? 'USD');
        $orderTotal = (float) $order->total_amount;
        $tolerance = ($orderCurrency === 'COP') ? 100.0 : 0.5; // Tolerancia permitida para discrepancias menores (centavos o redondeos COP)

        $rates = [
            'USD' => $resourceService->getExchangeRate('USD') ?: 1,
            'COP' => $resourceService->getExchangeRate('COP') ?: 1,
            'BS' => $resourceService->getExchangeRate('BS') ?: 1,
        ];

        $sumInOrderCurrency = 0;
        foreach ($payments as $p) {
            $amount = (float) ($p['amount'] ?? 0);
            $currency = strtoupper($p['currency'] ?? 'USD');
            $rate = $rates[$currency] ?? 1;
            
            if ($currency === $orderCurrency) {
                $sumInOrderCurrency += $amount;
            } else {
                $amountInBase = ($currency === 'USD') ? $amount : ($amount / $rate);
                $sumInOrderCurrency += $amountInBase * ($rates[$orderCurrency] ?? 1);
            }
        }

        $netPaid = $sumInOrderCurrency - $moneyReturns;

        if (abs($netPaid - $orderTotal) > $tolerance) {
            Log::warning("Discrepancia de pago bloqueada:", [
                'order_id' => $order->id,
                'total_paid' => $sumInOrderCurrency,
                'money_returns' => $moneyReturns,
                'net_paid' => $netPaid,
                'order_total' => $orderTotal,
                'diff' => abs($netPaid - $orderTotal)
            ]);

            throw new \App\Exceptions\PaymentDiscrepancyException(
                $netPaid,
                $orderTotal,
                $orderCurrency,
                'Discrepancia detectada: El pago neto (' . round($netPaid, 2) . ' ' . $orderCurrency . ') no coincide con el total de la factura (' . round($orderTotal, 2) . ' ' . $orderCurrency . '). Por favor, verifique los montos ingresados.'
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
                        $detail->price = ($itemData['price'] ?? 0);
                        $detail->price_before_discount = ($itemData['price_before_discount'] ?? 0);
                    } else {
                        $detail->price = ($itemData['price'] ?? 0);
                        $detail->price_before_discount = ($itemData['price_before_discount'] ?? 0);
                    }
                    if (isset($itemData['price_bs'])) {
                        $detail->price_bs = $itemData['price_bs'];
                    }
                    if (isset($itemData['price_before_discount_bs'])) {
                        $detail->price_before_discount_bs = $itemData['price_before_discount_bs'];
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
            // También cargamos la relación dish para los ítems de tipo plato (restaurante)
            $orderId->load(['details.product', 'details.dish']);
            $productIds = $orderId->details->where('product_type', '!=', 'dish')->pluck('product_id')->unique()->filter()->values()->all();
            
            // Si hay platos, extraer todos los product_id de sus ingredientes
            $dishDetails = $orderId->details->where('product_type', 'dish');
            if ($dishDetails->isNotEmpty()) {
                $dishIds = $dishDetails->pluck('dish_id')->unique()->all();
                $ingredientProductIds = DB::table('dish_ingredients')
                    ->whereIn('dish_id', $dishIds)
                    ->pluck('product_id')
                    ->unique()
                    ->all();
                $productIds = array_unique(array_merge($productIds, $ingredientProductIds));
            }

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
                if ($detail->product_type === 'dish') {
                    $dish = \App\Models\Dish::with('ingredients')->findOrFail($detail->dish_id);
                    foreach ($dish->ingredients as $ingredient) {
                        $presentation = $ingredient->presentation > 0 ? $ingredient->presentation : 1;
                        $quantityToReduce = ($ingredient->pivot->portion * $detail->quantity) / $presentation;
                        $lots = $lotsByProduct->get($ingredient->id, collect())->sortBy('expiration_date')->values();
                        
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
                            
                            // Registrar el movimiento de inventario con el lote exacto vendido
                            \App\Models\InventoryMovement::create([
                                'product_id' => $ingredient->id,
                                'product_lot_id' => $lot->id,
                                'movement_type' => 'sale',
                                'quantity' => -$taken,
                                'invoice_id' => null,
                                'supplier_id' => null,
                                'order_id' => $orderId->id,
                                'dish_id' => $dish->id,
                                'user_id' => $orderId->seller_id,
                                'stock_before' => $lot->quantity + $taken,
                                'stock_after' => $lot->quantity,
                                'movement_date' => \Carbon\Carbon::now(),
                            ]);
                        }
                        
                        if ($quantityToReduce > 0) {
                            $fallbackLot = ProductLot::where('product_id', $ingredient->id)->first();
                            if (!$fallbackLot) {
                                $fallbackLot = ProductLot::create([
                                    'product_id' => $ingredient->id,
                                    'lot_number' => 'S/L',
                                    'expiration_date' => \Carbon\Carbon::now()->addYears(5)->toDateString(),
                                    'quantity' => 0,
                                    'unit_cost' => $ingredient->unit_cost ?? 0,
                                ]);
                            }
                            
                            $fallbackLot->quantity -= $quantityToReduce;
                            ProductLot::withoutEvents(function () use ($fallbackLot) {
                                $fallbackLot->save();
                            });
                            
                            \App\Models\InventoryMovement::create([
                                'product_id' => $ingredient->id,
                                'product_lot_id' => $fallbackLot->id,
                                'movement_type' => 'sale',
                                'quantity' => -$quantityToReduce,
                                'invoice_id' => null,
                                'supplier_id' => null,
                                'order_id' => $orderId->id,
                                'dish_id' => $dish->id,
                                'user_id' => $orderId->seller_id,
                                'stock_before' => $fallbackLot->quantity + $quantityToReduce,
                                'stock_after' => $fallbackLot->quantity,
                                'movement_date' => \Carbon\Carbon::now(),
                            ]);
                            
                            $quantityToReduce = 0;
                        }
                    }
                } else {
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

                        // Registrar el movimiento de inventario con el lote exacto vendido
                        \App\Models\InventoryMovement::create([
                            'product_id' => $detail->product_id,
                            'product_lot_id' => $lot->id,
                            'movement_type' => 'sale',
                            'quantity' => -$taken,
                            'invoice_id' => null,
                            'supplier_id' => null,
                            'order_id' => $orderId->id,
                            'user_id' => $orderId->seller_id,
                            'stock_before' => $lot->quantity + $taken,
                            'stock_after' => $lot->quantity,
                            'movement_date' => \Carbon\Carbon::now(),
                        ]);


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
            }

            // Recalcular totales desde los detalles en BD (no confiar en el cliente)
            $orderId->updateTotals();
            $orderId->total_amount_usd = $orderId->details->sum(function ($d) {
                return ($d->unit_price_usd ?? 0) * ($d->quantity ?? 0);
            });

            // Validación de integridad financiera: el neto (pagos - vuelto) debe ser igual al total
            $this->validatePaymentsCoverOrderTotal($orderId, $request->payments, (float) ($request->changeAmount ?? 0));

            // Determinar si aplica Recargo Sujeto Pasivo Especial (SPE)
            $currency = strtoupper($orderId->currency);
            $isForeignCurrency = in_array($currency, ['USD', 'COP']);
            
            $hasIvaItems = $orderId->details->contains(function ($detail) {
                return optional($detail->product)->iva == 1;
            });

            $applySpe = false;
            if ($isForeignCurrency) {
                if ($hasIvaItems || mt_rand(1, 10) === 1) {
                    $applySpe = true;
                }
            }

            if ($applySpe) {
                $orderId->spe_surcharge_rate = 1.00;
                $orderId->spe_surcharge_amount = $orderId->total_amount * 0.01;
            } else {
                $orderId->spe_surcharge_rate = 0.00;
                $orderId->spe_surcharge_amount = 0.00;
            }
            $orderId->taxable_base = $orderId->total_amount;
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
                // El crédito siempre es por el total de la orden (solo completo)
                $creditAmount = (float) $orderId->total_amount;

                if ($creditAmount > 0) {
                    Credit::create([
                        'client_id' => $request->client_id,
                        'order_id' => $orderId->id,
                        'credit_amount' => $creditAmount,
                        'pending_amount' => $creditAmount,
                        'credit_date' => Carbon::now(),
                        'status' => 'Active'
                    ]);
                }
            }


            /*if ($isFiscalActive) {
                $this->invoicing($orderId, $request->spe);
                $ivaEjecuted = true;
            } else if ($request->generate_invoice) {
                $this->invoicing($orderId, $request->spe);
                $ivaEjecuted = true;
            }*/

            // Determinar si se genera la factura fiscal
            // 1. Moneda es Bolívares (BS)
            // 2. Tiene productos con IVA
            // 3. El vendedor marcó "generar factura" explícitamente en el request
            // 4. Se aplicó SPE (ya sea por tener IVA en divisas, o por selección aleatoria 1 de cada 10)
            $shouldInvoice = ($currency === 'BS') || $hasIvaItems || $request->generate_invoice || $applySpe;

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
                if (isset($request->changeAmount) && $request->changeAmount > 0) {
                    // El vuelto se dio físicamente en Pesos (COP)
                    $resourceService = app(\App\Services\Resources\ResourceService::class);
                                        $copRate = $resourceService->getExchangeRate('COPC') ?: 1;
                    
                    // Convertir el monto de USD a COP para restar correctamente del fondo en pesos
                    $copChangeAmount = $request->changeAmountUSD * $copRate;
                    
                    $current_cash->cop_cash -= $copChangeAmount;
                    $current_cash->cop_conversion += $copChangeAmount;
                } else {
                    // El vuelto se dio físicamente en Dólares (USD)
                    $current_cash->usd_cash -= $request->changeAmountUSD;
                }
                $current_cash->usd_conversion += $request->changeAmountUSD;
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

            $generalSettings = DB::table('general_settings')->first();
            $isRestaurant = $generalSettings && $generalSettings->business_type === 'restaurant';

            if (!$isRestaurant) {
                $alreadyReserved = Order::where('seller_id', $sellerId)
                    ->where('status', Order::RESERVED)
                    ->where('id', '!=', $order->id)
                    ->lockForUpdate()
                    ->exists();

                if ($alreadyReserved) {
                    throw new \Exception("Ya tienes una orden reservada. No puedes tener dos al mismo tiempo.");
                }
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

                $stockBefore = $item->product->stock ?? 0;
                $productLot->increment('quantity', $item->quantity);
                
                // Sincronizar stock del producto
                $totalStock = $item->product->lots()->sum('quantity');
                $item->product->updateQuietly(['stock' => $totalStock]);
                \App\Services\Inventory\StockoutService::syncStockout($item->product, $totalStock);

                // Crear el movimiento de inventario de tipo 'return' (devolución por cancelación) asociado a la orden
                \App\Models\InventoryMovement::create([
                    'product_id' => $item->product_id,
                    'product_lot_id' => $productLot->id,
                    'movement_type' => 'return',
                    'quantity' => $item->quantity,
                    'invoice_id' => null,
                    'supplier_id' => null,
                    'order_id' => $order->id,
                    'user_id' => \Illuminate\Support\Facades\Auth::id() ?? $order->seller_id,
                    'stock_before' => $stockBefore,
                    'stock_after' => $totalStock,
                    'movement_date' => now(),
                ]);
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
                        case 'debit_card':
                            $cashClosing->bs_card_debito -= $amount;
                            break;
                        case 'credit_card':
                            $cashClosing->bs_card_credit -= $amount;
                            break;
                        case 'cash_cop':
                            if (isset($order->money_returns) && $order->money_returns > 0.0) {
                                // Si se dio vuelto en pesos, el monto neto cobrado en COP es el pago - vuelto
                                $montoDescCOP = $amount - $order->money_returns;
                                $cashClosing->cop_cash -= $montoDescCOP;
                                $cashClosing->cop_conversion -= $order->money_returns ?? null;
                            } else {
                                $cashClosing->cop_cash -= $amount;
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

                $cashClosing->recalculateTotals();
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

    /**
     * Aplica las promociones generales a los ítems de la orden.
     * Soporta: 2x1, 3x2, 50% en el segundo, y precio fijo por categoría.
     */
    public function applyGeneralPromotions(Order $order): void
    {
        // 1. Obtener todas las promociones generales activas
        $promotions = \App\Models\GeneralPromotion::where('is_active', true)->get();
        if ($promotions->isEmpty()) {
            // Si no hay promociones activas, restaurar precios normales si tenían descuento general anterior
            $details = $order->details()->with(['product', 'dish'])->get();
            foreach ($details as $detail) {
                if ($detail->discount_type === 'general') {
                    $detail->discount_percentage = null;
                    $detail->discount_type = null;
                    // Restaurar precio base según la moneda actual
                    $this->restoreBasePrice($order, $detail);
                    $detail->save();
                }
            }
            return;
        }

        // Cargar los detalles de la orden
        $details = $order->details()->with(['product', 'dish'])->get();

        // 2. Primero restaurar los detalles que tengan descuento general viejo, para tener base limpia
        foreach ($details as $detail) {
            if ($detail->discount_type === 'general') {
                $detail->discount_percentage = null;
                $detail->discount_type = null;
                $this->restoreBasePrice($order, $detail);
            }
        }

        // 3. Procesar Precio Fijo (fixed_price) primero, ya que altera el precio base del ítem
        $fixedPricePromos = $promotions->where('type', 'fixed_price');
        if ($fixedPricePromos->isNotEmpty()) {
            foreach ($details as $detail) {
                // Obtener ID de categoría del producto o plato
                $categoryId = $detail->product_type === 'dish' ? ($detail->dish->category_id ?? null) : ($detail->product->category_id ?? null);
                if (!$categoryId) continue;

                foreach ($fixedPricePromos as $promo) {
                    if (is_null($promo->fixed_price)) continue;
                    if (is_array($promo->categories) && in_array($categoryId, $promo->categories)) {
                        // Aplicar el precio fijo especificado en la promoción. 
                        // El precio fijo viene en USD, hay que convertir a la moneda de la orden.
                        $priceInOrderCurrency = $promo->fixed_price;
                        $usdPrice = $promo->fixed_price;

                        if ($order->currency === 'COP') {
                            $resourceService = app(\App\Services\Resources\ResourceService::class);
                            $tasaCop = $resourceService->getExchangeRate('COP') ?: 1;
                            $priceInOrderCurrency = ceil(($promo->fixed_price * $tasaCop) / 100) * 100;
                        } elseif ($order->currency === 'BS') {
                            $resourceService = app(\App\Services\Resources\ResourceService::class);
                            $tasaBs = $resourceService->getExchangeRate('BS') ?: 1;
                            $priceInOrderCurrency = round($promo->fixed_price * $tasaBs, 2);
                        }

                        $detail->price = $priceInOrderCurrency;
                        $detail->unit_price_usd = $usdPrice;
                        // Marcar que fue afectado por promoción general
                        $detail->discount_percentage = 0; // Opcional, solo para indicar que aplica promo
                        $detail->discount_type = 'general';
                        $detail->discount_source_id = $promo->id;
                        break;
                    }
                }
            }
        }

        // 4. Agrupar los detalles por categoría
        $detailsByCategory = [];
        foreach ($details as $detail) {
            $categoryId = $detail->product_type === 'dish' ? ($detail->dish->category_id ?? null) : ($detail->product->category_id ?? null);
            if (!$categoryId) continue;
            $detailsByCategory[$categoryId][] = $detail;
        }

        // 5. Procesar ofertas de volumen (2x1, 3x2, 50_second)
        $volumePromos = $promotions->whereIn('type', ['2x1', '3x2', '50_second']);

        foreach ($detailsByCategory as $categoryId => $categoryDetails) {
            // Buscar si hay una promoción aplicable a esta categoría
            $applicablePromo = null;
            foreach ($volumePromos as $promo) {
                if (is_array($promo->categories) && in_array($categoryId, $promo->categories)) {
                    $applicablePromo = $promo;
                    break;
                }
            }

            if (!$applicablePromo) continue;

            // Desglosar la cantidad de ítems a nivel individual de unidad (para poder ordenar por precio individual)
            $flatItems = [];
            foreach ($categoryDetails as $detail) {
                $qty = (int) $detail->quantity;
                // Usamos el precio unitario del ítem
                $unitPrice = (float) $detail->price;
                $unitPriceUsd = (float) $detail->unit_price_usd;

                for ($i = 0; $i < $qty; $i++) {
                    $flatItems[] = [
                        'detail' => $detail,
                        'price' => $unitPrice,
                        'unit_price_usd' => $unitPriceUsd,
                        'discount_percentage' => 0,
                    ];
                }
            }

            // Ordenar de mayor a menor precio
            usort($flatItems, function ($a, $b) {
                return $b['price'] <=> $a['price'];
            });

            $totalItemsCount = count($flatItems);

            if ($applicablePromo->type === '2x1') {
                // Paga el de mayor valor y el de menor de la misma categoría sale gratis
                // Agrupamos en pares: (1ero, 2do), (3ero, 4to), etc.
                for ($i = 0; $i < $totalItemsCount; $i += 2) {
                    if ($i + 1 < $totalItemsCount) {
                        // El segundo elemento de la pareja (menor o igual valor) sale gratis (100% descuento)
                        $flatItems[$i + 1]['discount_percentage'] = 100;
                    }
                }
            } elseif ($applicablePromo->type === '3x2') {
                // Paga los dos más caros, el tercero (más barato) sale gratis
                // Agrupamos en tríos: (1ero, 2do, 3ero), (4to, 5to, 6to), etc.
                for ($i = 0; $i < $totalItemsCount; $i += 3) {
                    if ($i + 2 < $totalItemsCount) {
                        // El tercer elemento del trío (menor valor) sale gratis (100% descuento)
                        $flatItems[$i + 2]['discount_percentage'] = 100;
                    }
                }
            } elseif ($applicablePromo->type === '50_second') {
                // Paga el más caro, el segundo (más barato) sale al 50%
                // Agrupamos en parejas: (1ero, 2do), (3ero, 4to), etc.
                for ($i = 0; $i < $totalItemsCount; $i += 2) {
                    if ($i + 1 < $totalItemsCount) {
                        // El segundo elemento de la pareja (menor valor) sale con 50% de descuento
                        $flatItems[$i + 1]['discount_percentage'] = 50;
                    }
                }
            }

            // Consolidar los descuentos de vuelta en los objetos de detalle correspondientes
            // Dado que un mismo OrderDetail puede tener cantidad > 1 y las unidades individuales pueden tener descuentos diferentes,
            // si un detalle se divide en "unidades con descuento" y "unidades sin descuento",
            // calculamos el descuento promedio ponderado para esa línea de detalle.
            $discountsByDetailId = [];
            foreach ($flatItems as $item) {
                $detailId = $item['detail']->id;
                if (!isset($discountsByDetailId[$detailId])) {
                    $discountsByDetailId[$detailId] = [];
                }
                $discountsByDetailId[$detailId][] = $item['discount_percentage'];
            }

            foreach ($categoryDetails as $detail) {
                $itemDiscounts = $discountsByDetailId[$detail->id] ?? [];
                if (empty($itemDiscounts)) continue;

                // Promedio de descuento de las unidades de este detalle
                $avgDiscount = array_sum($itemDiscounts) / count($itemDiscounts);

                if ($avgDiscount > 0) {
                    // Primero restaurar el precio original antes de aplicar el nuevo descuento promedio general
                    $this->restoreBasePrice($order, $detail);

                    $detail->discount_percentage = $avgDiscount;
                    $detail->discount_type = 'general';
                    $detail->discount_source_id = $applicablePromo->id;
                    $detail->price = $detail->price * (1 - ($avgDiscount / 100));
                    $detail->unit_price_usd = $detail->unit_price_usd * (1 - ($avgDiscount / 100));
                }
            }
        }

        // Guardar todos los detalles modificados en la BD
        foreach ($details as $detail) {
            $detail->save();
        }
    }

    /**
     * Restaura el precio original del producto o plato de un detalle de la orden.
     */
    private function restoreBasePrice(Order $order, OrderDetail $detail): void
    {
        $targetCurrency = $order->currency;
        if ($detail->product_type === 'dish') {
            $dish = $detail->dish;
            $usdPrice = $dish->designated_price ?? 0;
            if ($targetCurrency === 'USD') {
                $priceToSet = $usdPrice;
            } elseif ($targetCurrency === 'COP') {
                $resourceService = app(\App\Services\Resources\ResourceService::class);
                $tasaCop = $resourceService->getExchangeRate('COP') ?: 1;
                $priceToSet = ceil(($usdPrice * $tasaCop) / 100) * 100;
            } elseif ($targetCurrency === 'BS') {
                $resourceService = app(\App\Services\Resources\ResourceService::class);
                $tasaBs = $resourceService->getExchangeRate('BS') ?: 1;
                $priceToSet = round($usdPrice * $tasaBs, 2);
            } else {
                $priceToSet = $usdPrice;
            }
        } else {
            $product = $detail->product;
            $pricingStrategy = \App\Services\Order\Strategies\PricingStrategyFactory::make($targetCurrency);
            $priceToSet = $pricingStrategy->calculatePrice($product, $detail);
            $usdPrice = $product->sale_price;
        }

        $detail->price = $priceToSet;
        $detail->unit_price_usd = $usdPrice;
    }
}
