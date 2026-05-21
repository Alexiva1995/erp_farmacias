<?php

namespace Tests\Feature\Products;

use App\Models\Product;
use App\Models\ProductPack;
use App\Repositories\DiscountReportRepository;
use App\Models\User;
use App\Models\Order;
use App\Models\OrderDetail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductPackOptimizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_product_pack_config_syncs_to_pivot_table_automatically(): void
    {
        $product1 = Product::create([
            'name' => 'PRODUCT 1',
            'active_ingredient' => 'INGREDIENT 1',
            'barcode' => 'Barcode-Product-1',
            'unit_cost' => 8.00,
            'sale_price' => 10.00,
            'iva' => false,
            'psychotropic' => false,
            'is_colombian_origin' => false,
            'is_deleted' => false,
            'is_active' => true,
            'stock' => 100,
        ]);

        $product2 = Product::create([
            'name' => 'PRODUCT 2',
            'active_ingredient' => 'INGREDIENT 2',
            'barcode' => 'Barcode-Product-2',
            'unit_cost' => 16.00,
            'sale_price' => 20.00,
            'iva' => false,
            'psychotropic' => false,
            'is_colombian_origin' => false,
            'is_deleted' => false,
            'is_active' => true,
            'stock' => 100,
        ]);

        // Crear un pack manipulando solo pack_config JSON
        $pack = ProductPack::create([
            'name' => 'Pack Test Ahorro',
            'is_active' => true,
            'pack_config' => [
                $product1->id => [
                    'quantity' => 2,
                    'discount_percentage' => 10.00,
                    'sale_price' => 9.00
                ],
                $product2->id => [
                    'quantity' => 1,
                    'discount_percentage' => 15.00,
                    'sale_price' => 17.00
                ]
            ],
            'total_price' => 35.00
        ]);

        // Validar que se crearon los registros en la tabla pivote física
        $this->assertDatabaseHas('product_pack_items', [
            'pack_id' => $pack->id,
            'product_id' => $product1->id,
            'quantity' => 2,
            'discount_percentage' => 10.00,
            'sale_price' => 9.00
        ]);

        $this->assertDatabaseHas('product_pack_items', [
            'pack_id' => $pack->id,
            'product_id' => $product2->id,
            'quantity' => 1,
            'discount_percentage' => 15.00,
            'sale_price' => 17.00
        ]);

        // Actualizar el pack con otra configuración
        $pack->update([
            'pack_config' => [
                $product1->id => [
                    'quantity' => 5,
                    'discount_percentage' => 20.00,
                    'sale_price' => 8.00
                ]
            ]
        ]);

        // Validar que se removió el product2 y se actualizó el product1
        $this->assertDatabaseHas('product_pack_items', [
            'pack_id' => $pack->id,
            'product_id' => $product1->id,
            'quantity' => 5,
            'discount_percentage' => 20.00,
            'sale_price' => 8.00
        ]);

        $this->assertDatabaseMissing('product_pack_items', [
            'pack_id' => $pack->id,
            'product_id' => $product2->id
        ]);
    }

    public function test_discount_report_repository_calculates_correct_money_given_using_pivot(): void
    {
        $product = Product::create([
            'name' => 'PRODUCT SUPER',
            'active_ingredient' => 'INGREDIENT SUPER',
            'barcode' => 'Barcode-Product-Super',
            'unit_cost' => 80.00,
            'sale_price' => 100.00,
            'iva' => false,
            'psychotropic' => false,
            'is_colombian_origin' => false,
            'is_deleted' => false,
            'is_active' => true,
            'stock' => 100,
        ]);

        $seller = User::create([
            'username' => 'seller_pack',
            'email' => 'seller_pack@example.com',
            'password_hash' => bcrypt('password'),
            'is_active' => true,
        ]);

        $pack = ProductPack::create([
            'name' => 'Pack Super Descuento',
            'is_active' => true,
            'pack_config' => [
                $product->id => [
                    'quantity' => 1,
                    'discount_percentage' => 20.00, // 20% descuento
                    'sale_price' => 80.00
                ]
            ],
            'total_price' => 80.00
        ]);

        // Crear una orden con este pack
        $order = Order::create([
            'seller_id' => $seller->id,
            'status' => 'Completed',
            'order_date' => now()->format('Y-m-d H:i:s'),
            'total_amount' => 80.00,
            'total_amount_usd' => 80.00,
            'money_returns' => 0.00,
            'currency' => 'USD',
            'payment_methods' => [],
            'created_at' => now()->format('Y-m-d H:i:s'),
        ]);

        OrderDetail::create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'pack_id' => $pack->id,
            'product_type' => 'pack',
            'quantity' => 1,
            'price' => 80.00,
            'unit_cost' => 80.00,
            'unit_price_usd' => 80.00,
            'price_before_discount' => 100.00,
            'discount_percentage' => 0 // el descuento es por pack
        ]);

        // Calcular el impacto financiero mediante el repositorio refactorizado
        $repository = new DiscountReportRepository();
        $kpis = $repository->getKPIs([
            'start_date' => now()->startOfMonth()->format('Y-m-d'),
            'end_date' => now()->endOfMonth()->format('Y-m-d'),
        ]);

        // Con 20% de descuento en el pack:
        // Precio original = 100
        // Descuento = 20%
        // Venta = 80
        // Dinero Cedido = Venta * (Descuento / (100 - Descuento)) = 80 * (20 / 80) = 20.00 USD
        $this->assertEquals(20.00, $kpis['total_money_given']);
        $this->assertEquals(80.00, $kpis['total_sales_with_discount']);
        $this->assertEquals(20.00, $kpis['avg_global_discount']);
    }
}
