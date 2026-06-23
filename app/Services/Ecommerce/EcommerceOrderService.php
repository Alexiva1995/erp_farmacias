<?php

namespace App\Services\Ecommerce;

use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Support\Facades\DB;
use Exception;

class EcommerceOrderService
{
    /**
     * Procesar y crear una nueva orden de e-commerce.
     *
     * @param array $orderData Datos del cliente y dirección.
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
            // 1. Calcular total del pedido y verificar disponibilidad de stock
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
                    // Si no tiene variante, validar y descontar stock global del producto
                    if ($product->stock < $item['quantity']) {
                        throw new Exception("Stock insuficiente para el producto '{$product->name}'.");
                    }
                    $product->decrement('stock', $item['quantity']);
                }

                $subtotal = $unitPrice * $item['quantity'];
                $totalAmount += $subtotal;

                $itemsToInsert[] = [
                    'product_id' => $product->id,
                    'product_variant_id' => $variant ? $variant->id : null,
                    'quantity' => $item['quantity'],
                    'price' => $unitPrice,
                ];
            }

            // 2. Crear la orden de e-commerce
            $order = DB::table('ecommerce_orders')->insertGetId([
                'customer_name' => $orderData['customer_name'],
                'customer_email' => $orderData['customer_email'],
                'customer_phone' => $orderData['customer_phone'] ?? null,
                'shipping_address' => $orderData['shipping_address'],
                'total_amount' => $totalAmount,
                'status' => 'Pending',
                'payment_method' => $orderData['payment_method'] ?? 'Simulated',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            // 3. Registrar los ítems vinculados a la orden creada
            foreach ($itemsToInsert as $item) {
                DB::table('ecommerce_order_items')->insert([
                    'ecommerce_order_id' => $order,
                    'product_id' => $item['product_id'],
                    'product_variant_id' => $item['product_variant_id'],
                    'quantity' => $item['quantity'],
                    'price' => $item['price'],
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            return [
                'success' => true,
                'order_id' => $order,
                'total_amount' => $totalAmount,
            ];
        });
    }
}
