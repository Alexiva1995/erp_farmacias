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

        return DB::transaction(function () use ($orderData, $cartItems) {
            // 1. Calcular total en USD (precio base de los productos)
            $totalAmount = 0;
            $itemsToInsert = [];

            foreach ($cartItems as $item) {
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

                    // Validar y descontar stock de variante
                    if ($variant->stock < $item['quantity']) {
                        throw new Exception("Stock insuficiente para el producto '{$product->name}' en la variante seleccionada.");
                    }
                    $variant->decrement('stock', $item['quantity']);
                } else {
                    // Sin variante: validar y descontar stock global
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

            // 2. Calcular total en la moneda del cliente usando tasas de cambio actuales
            $currency          = strtoupper($orderData['payment_currency'] ?? 'USD');
            $totalInCurrency   = $this->convertToClientCurrency($totalAmount, $currency);

            // 3. Resolver el usuario 'tienda' para asociar el pedido
            $tiendaUser   = DB::table('users')->where('username', 'tienda')->first();
            $tiendaUserId = $tiendaUser ? $tiendaUser->id : null;

            // 4. Crear la orden de e-commerce con moneda del cliente
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
