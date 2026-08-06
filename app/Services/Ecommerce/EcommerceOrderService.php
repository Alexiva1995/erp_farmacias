<?php

declare(strict_types=1);

namespace App\Services\Ecommerce;

use App\Models\ExchangeRate;
use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Support\Facades\DB;
use Exception;

class EcommerceOrderService
{
    /**
     * Procesar y crear una nueva orden de e-commerce.
     * Calcula el total en USD (precio base) y también en la moneda del cliente.
     *
     * @param array $orderData Datos del cliente, dirección y método de pago.
     * @param array $cartItems Ítems del carrito.
     * @return array
     * @throws Exception
     */
    public function createOrder(array $orderData, array $cartItems): array
    {
        if (empty($cartItems)) {
            throw new Exception("El carrito de compras no puede estar vacío.");
        }

        $isRestaurant = \App\Models\GeneralSetting::first()?->business_type === 'restaurant';

        return DB::transaction(function () use ($orderData, $cartItems, $isRestaurant) {
            // 1. Calcular total en USD (precio base de los productos o platos)
            $totalAmount = 0;
            $itemsToInsert = [];

            foreach ($cartItems as $item) {
                if ($isRestaurant) {
                    $dish = \App\Models\Dish::find($item['product_id']);
                    if (!$dish) {
                        throw new Exception("El plato con ID {$item['product_id']} no existe.");
                    }

                    $unitPrice = (float) $dish->designated_price;
                    $subtotal    = $unitPrice * $item['quantity'];
                    $totalAmount += $subtotal;

                    $itemsToInsert[] = [
                        'product_id'         => $dish->id,
                        'product_variant_id' => null,
                        'quantity'           => $item['quantity'],
                        'price'              => $unitPrice,
                    ];
                } else {
                    $product = Product::find($item['product_id']);
                    if (!$product) {
                        throw new Exception("El producto con ID {$item['product_id']} no existe.");
                    }

                    $variant = null;
                    $unitPrice = (float) $product->sale_price;

                    if (!empty($item['variant_id'])) {
                        $variant = ProductVariant::find($item['variant_id']);
                        if (!$variant || $variant->product_id !== $product->id) {
                            throw new Exception("La variante seleccionada no es válida para el producto.");
                        }

                        // Ajustar precio según el modificador de la variante
                        $unitPrice += (float) $variant->price_modifier;
                    }

                    // Validar y descontar stock
                    if ($variant) {
                        if ($variant->stock < $item['quantity']) {
                            throw new Exception("Stock insuficiente para la variante del producto '{$product->name}'.");
                        }
                        $variant->decrement('stock', $item['quantity']);
                    } else {
                        if ($product->stock < $item['quantity']) {
                            throw new Exception("Stock insuficiente para el producto '{$product->name}'.");
                        }
                        $product->decrement('stock', $item['quantity']);
                    }

                    $subtotal    = $unitPrice * $item['quantity'];
                    $totalAmount += $subtotal;

                    $itemsToInsert[] = [
                        'product_id'         => $product->id,
                        'product_variant_id' => $variant ? $variant->id : null,
                        'quantity'           => $item['quantity'],
                        'price'              => $unitPrice,
                    ];
                }
            }

            // 2. Calcular total en la moneda del cliente usando tasas de cambio actuales
            $currency          = strtoupper($orderData['payment_currency'] ?? 'USD');
            $totalInCurrency   = $this->convertToClientCurrency($totalAmount, $currency);

            // 3. Registrar o buscar cliente en BD si no existe
            if (!empty($orderData['customer_document_number'])) {
                $docType  = $orderData['customer_document_type'] ?? 'V-';
                $docNum   = trim($orderData['customer_document_number']);
                $cleanNum = preg_replace('/[^0-9]/', '', $docNum);

                $existingClient = \App\Models\Client::where('identification', $docNum)
                    ->orWhere('identification', $docType . $docNum)
                    ->orWhere('identification', $cleanNum)
                    ->orWhere('identification', 'like', '%' . $cleanNum . '%')
                    ->first();

                if ($existingClient) {
                    $fullName  = trim($orderData['customer_name'] ?? '');
                    $parts     = explode(' ', $fullName, 2);
                    $firstName = $parts[0] ?? $existingClient->name;
                    $lastName  = $parts[1] ?? $existingClient->last_name;

                    $existingClient->update([
                        'name'      => $firstName ?: $existingClient->name,
                        'last_name' => $lastName ?: $existingClient->last_name,
                        'email'     => !empty($orderData['customer_email']) ? $orderData['customer_email'] : $existingClient->email,
                        'phone'     => !empty($orderData['customer_phone']) ? $orderData['customer_phone'] : $existingClient->phone,
                        'address'   => !empty($orderData['shipping_address']) ? $orderData['shipping_address'] : $existingClient->address,
                    ]);
                } else {
                    $fullName  = trim($orderData['customer_name'] ?? '');
                    $parts     = explode(' ', $fullName, 2);
                    $firstName = $parts[0] ?? 'Cliente';
                    $lastName  = $parts[1] ?? '';

                    \App\Models\Client::create([
                        'identification_type' => $docType,
                        'identification'      => $docNum,
                        'name'                => $firstName,
                        'last_name'           => $lastName,
                        'email'               => !empty($orderData['customer_email']) ? $orderData['customer_email'] : null,
                        'phone'               => !empty($orderData['customer_phone']) ? $orderData['customer_phone'] : null,
                        'address'             => !empty($orderData['shipping_address']) ? $orderData['shipping_address'] : null,
                        'is_active'           => true,
                    ]);
                }
            }

            // 4. Resolver el usuario 'tienda' para asociar el pedido
            $tiendaUser   = DB::table('users')->where('username', 'tienda')->first();
            $tiendaUserId = $tiendaUser ? $tiendaUser->id : null;

            // 5. Crear la orden de e-commerce con moneda del cliente
            $orderId = DB::table('ecommerce_orders')->insertGetId([
                'user_id'                  => $tiendaUserId,
                'customer_name'            => $orderData['customer_name'],
                'customer_email'           => $orderData['customer_email'] ?? null,
                'customer_phone'           => $orderData['customer_phone'] ?? null,
                'customer_document_type'   => $orderData['customer_document_type'] ?? 'V-',
                'customer_document_number' => $orderData['customer_document_number'] ?? null,
                'shipping_address'         => $orderData['shipping_address'] ?? '',
                'total_amount'             => $totalAmount,           // En USD (precio base)
                'currency'                 => $currency,              // Moneda del cliente
                'total_in_currency'        => $totalInCurrency,       // Monto en moneda del cliente
                'status'                   => 'Pending',
                'payment_method'           => $orderData['payment_method'] ?? 'Simulated',
                'created_at'               => now(),
                'updated_at'               => now(),
            ]);

            // 5. Registrar los ítems vinculados a la orden creada
            foreach ($itemsToInsert as $item) {
                DB::table('ecommerce_order_items')->insert([
                    'ecommerce_order_id'  => $orderId,
                    'product_id'          => $item['product_id'],
                    'product_variant_id'  => $item['product_variant_id'],
                    'quantity'            => $item['quantity'],
                    'price'               => $item['price'],
                    'created_at'          => now(),
                    'updated_at'          => now(),
                ]);
            }

            return [
                'success'          => true,
                'order_id'         => $orderId,
                'total_amount'     => $totalAmount,
                'currency'         => $currency,
                'total_in_currency' => $totalInCurrency,
            ];
        });
    }

    /**
     * Obtener listado paginado de pedidos e-commerce para el panel administrativo.
     */
    public function getAdminOrders(array $filters, int $perPage = 15)
    {
        $query = DB::table('ecommerce_orders')
            ->select('ecommerce_orders.*', 'users.username as assigned_user')
            ->leftJoin('users', 'users.id', '=', 'ecommerce_orders.user_id');

        if (!empty($filters['start_date'])) {
            $query->where('ecommerce_orders.created_at', '>=', $filters['start_date'] . ' 00:00:00');
        }
        if (!empty($filters['end_date'])) {
            $query->where('ecommerce_orders.created_at', '<=', $filters['end_date'] . ' 23:59:59');
        }

        $paginated = $query->orderBy('ecommerce_orders.id', 'desc')->paginate($perPage);

        if ($paginated->isEmpty()) {
            return $paginated;
        }

        $orderIds = collect($paginated->items())->pluck('id')->toArray();

        $items = DB::table('ecommerce_order_items')
            ->join('products', 'products.id', '=', 'ecommerce_order_items.product_id')
            ->leftJoin('product_variants', 'product_variants.id', '=', 'ecommerce_order_items.product_variant_id')
            ->select(
                'ecommerce_order_items.*', 
                'products.name as product_name', 
                'product_variants.attribute_value as variant_value'
            )
            ->whereIn('ecommerce_order_id', $orderIds)
            ->get()
            ->groupBy('ecommerce_order_id');

        foreach ($paginated->items() as $order) {
            $order->items = $items->get($order->id) ?? [];
        }

        return $paginated;
    }

    /**
     * Aprobar una orden de e-commerce (el pago fue confirmado).
     */
    public function approveOrder(int $id): bool
    {
        return DB::transaction(function () use ($id) {
            $ecommerceOrder = DB::table('ecommerce_orders')->where('id', $id)->first();

            if (!$ecommerceOrder) {
                throw new Exception('La orden no fue encontrada.');
            }

            if ($ecommerceOrder->status !== 'Pending') {
                throw new Exception('Solo se pueden aprobar órdenes en estado Pendiente.');
            }

            DB::table('ecommerce_orders')
                ->where('id', $id)
                ->update(['status' => 'Paid', 'updated_at' => now()]);

            if (!empty($ecommerceOrder->tpv_order_id)) {
                \App\Models\Order::where('id', $ecommerceOrder->tpv_order_id)
                    ->update(['status' => 'Completed']);
            }

            return true;
        });
    }

    /**
     * Cancelar una orden de e-commerce y devolver stock.
     */
    public function cancelOrder(int $id): bool
    {
        return DB::transaction(function () use ($id) {
            $ecommerceOrder = DB::table('ecommerce_orders')->where('id', $id)->first();

            if (!$ecommerceOrder) {
                throw new Exception('La orden no fue encontrada.');
            }

            $items = DB::table('ecommerce_order_items')
                ->where('ecommerce_order_id', $id)
                ->get();

            foreach ($items as $item) {
                if (!empty($item->product_variant_id)) {
                    DB::table('product_variants')
                        ->where('id', $item->product_variant_id)
                        ->increment('stock', $item->quantity);
                } else {
                    DB::table('products')
                        ->where('id', $item->product_id)
                        ->increment('stock', $item->quantity);
                }
            }

            DB::table('ecommerce_orders')
                ->where('id', $id)
                ->update(['status' => 'Cancelled', 'updated_at' => now()]);

            if (!empty($ecommerceOrder->tpv_order_id)) {
                \App\Models\Order::where('id', $ecommerceOrder->tpv_order_id)
                    ->update(['status' => 'cancelled']);
            }

            return true;
        });
    }

    /**
     * Marcar pedido como enviado.
     */
    public function shipOrder(int $id): bool
    {
        return DB::transaction(function () use ($id) {
            $ecommerceOrder = DB::table('ecommerce_orders')->where('id', $id)->first();

            if (!$ecommerceOrder) {
                throw new Exception('La orden no fue encontrada.');
            }

            DB::table('ecommerce_orders')
                ->where('id', $id)
                ->update(['status' => 'Shipped', 'updated_at' => now()]);

            return true;
        });
    }

    /**
     * Completar el pedido e-commerce.
     */
    public function completeOrder(int $id): int
    {
        return DB::transaction(function () use ($id) {
            $ecommerceOrder = DB::table('ecommerce_orders')->where('id', $id)->first();

            if (!$ecommerceOrder) {
                throw new Exception('La orden no existe.');
            }

            if ($ecommerceOrder->status === 'Completed') {
                throw new Exception('La orden ya fue completada anteriormente.');
            }

            if ($ecommerceOrder->status === 'Cancelled') {
                throw new Exception('Una orden cancelada no se puede completar.');
            }

            DB::table('ecommerce_orders')
                ->where('id', $id)
                ->update(['status' => 'Completed', 'updated_at' => now()]);

            if (!empty($ecommerceOrder->tpv_order_id)) {
                \App\Models\Order::where('id', $ecommerceOrder->tpv_order_id)
                    ->whereNotIn('status', ['Completed', 'cancelled'])
                    ->update(['status' => 'Completed']);
            }

            return (int) ($ecommerceOrder->tpv_order_id ?? 0);
        });
    }

    /**
     * Convierte un monto en USD a la moneda del cliente aplicando la tasa activa.
     * Los precios de los productos están guardados en USD.
     */
    private function convertToClientCurrency(float $amountUsd, string $currency): float
    {
        if ($currency === 'USD') {
            return round($amountUsd, 2);
        }

        // Buscar la tasa de cambio activa para la moneda objetivo
        $rateObj = ExchangeRate::where('currency_code', $currency)->latest()->first();

        if (!$rateObj || (float) $rateObj->rate <= 0) {
            // Sin tasa disponible: retornar el monto en USD como fallback
            return round($amountUsd, 2);
        }

        // La tasa almacenada es USD → moneda_local (ej. 1 USD = 4200 COP o 1 USD = 45 VES)
        return round($amountUsd * (float) $rateObj->rate, 2);
    }
}

