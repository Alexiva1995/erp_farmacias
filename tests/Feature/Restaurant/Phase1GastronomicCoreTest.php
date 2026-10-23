<?php

declare(strict_types=1);

namespace Tests\Feature\Restaurant;

use App\Enums\ModifierTypeEnum;
use App\Models\Category;
use App\Models\Dish;
use App\Models\GeneralSetting;
use App\Models\ModifierGroup;
use App\Models\ModifierOption;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductLot;
use App\Models\Role;
use App\Models\User;
use App\Services\Order\OrderActionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class Phase1GastronomicCoreTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected Category $category;

    protected function setUp(): void
    {
        parent::setUp();

        $role = Role::firstOrCreate(['id' => 1], [
            'name' => 'Admin',
            'guard_name' => 'web',
            'menu_permissions' => ['*'],
        ]);

        $this->user = User::factory()->create(['role_id' => $role->id]);
        $this->actingAs($this->user);

        GeneralSetting::updateOrCreate(
            ['id' => 1],
            [
                'app_name' => 'Restaurante Test',
                'business_type' => 'restaurant',
                'tpv_style' => 'restaurant',
                'enable_dishes' => true,
                'enable_modifiers' => true,
                'fiscal_mode' => 'demo',
            ]
        );

        $this->category = Category::create([
            'name' => 'Comida Rápida',
            'type' => 'dishes',
        ]);
    }

    public function test_can_create_dish_with_iva_and_modifier_groups(): void
    {
        $group = ModifierGroup::create([
            'name' => 'Término de Carne',
            'min_selections' => 1,
            'max_selections' => 1,
            'is_active' => true,
        ]);

        $productIngredient = Product::factory()->create([
            'name' => 'Carne Molida',
            'unit_cost' => 2.0,
            'presentation' => 1,
            'unit_of_measure' => 'kg',
        ]);

        $payload = [
            'name' => 'Hamburguesa Especial',
            'category_id' => $this->category->id,
            'percentage_profit' => 1.5,
            'cost_price' => 2.0,
            'suggested_price' => 5.0,
            'designated_price' => 6.0,
            'status' => 1,
            'iva' => true,
            'tax_rate' => 16.00,
            'preparation_time_minutes' => 20,
            'modifier_group_ids' => [$group->id],
            'ingredients' => [
                [
                    'product_id' => $productIngredient->id,
                    'portion' => 0.25,
                    'designated_cost' => 0.50,
                ],
            ],
        ];

        $response = $this->postJson('/api/dishes', $payload);
        $response->assertStatus(201);
        $response->assertJsonPath('data.name', 'Hamburguesa Especial');
        $response->assertJsonPath('data.iva', true);
        $response->assertJsonPath('data.tax_rate', 16);

        $this->assertDatabaseHas('dishes', [
            'name' => 'Hamburguesa Especial',
            'iva' => true,
            'tax_rate' => 16.00,
            'preparation_time_minutes' => 20,
        ]);

        $this->assertDatabaseHas('dish_modifier_group', [
            'modifier_group_id' => $group->id,
        ]);
    }

    public function test_order_totals_and_invoicing_apply_iva_to_taxable_dishes(): void
    {
        $dishTaxable = Dish::create([
            'name' => 'Pizza Familiar Gravada',
            'category_id' => $this->category->id,
            'designated_price' => 10.00,
            'cost_price' => 4.00,
            'suggested_price' => 8.00,
            'percentage_profit' => 1.5,
            'status' => 1,
            'iva' => true,
            'tax_rate' => 16.00,
        ]);

        $order = Order::create([
            'seller_id' => $this->user->id,
            'currency' => 'USD',
            'status' => Order::PENDING,
            'total_amount' => 0,
            'total_amount_usd' => 0,
            'money_returns' => 0,
        ]);

        $order->details()->create([
            'dish_id' => $dishTaxable->id,
            'quantity' => 1,
            'price' => 10.00,
            'unit_cost' => 4.00,
            'unit_price_usd' => 10.00,
            'product_type' => 'dish',
        ]);

        $order->updateTotals();
        $order->refresh();

        // 10.00 + 16% IVA = 11.60
        $this->assertEquals(11.60, (float) $order->total_amount);

        // Prueba de facturación fiscal
        $actionService = app(OrderActionService::class);
        $fiscal = $actionService->invoicing($order, 0);

        $this->assertNotNull($fiscal);
        $this->assertGreaterThan(0, (float) $fiscal->iva_amount);
        $this->assertGreaterThan(0, (float) $fiscal->taxable_amount);
    }

    public function test_can_have_multiple_lines_of_same_dish_with_different_modifiers_or_notes(): void
    {
        $dish = Dish::create([
            'name' => 'Hamburguesa Doble',
            'category_id' => $this->category->id,
            'designated_price' => 8.00,
            'cost_price' => 3.00,
            'suggested_price' => 6.00,
            'percentage_profit' => 1.5,
            'status' => 1,
            'iva' => false,
        ]);

        $group = ModifierGroup::create([
            'name' => 'Extras',
            'min_selections' => 0,
            'max_selections' => 5,
        ]);

        $optionExtraBacon = ModifierOption::create([
            'modifier_group_id' => $group->id,
            'name' => 'Extra Tocineta',
            'modifier_type' => ModifierTypeEnum::ADD,
            'extra_price_usd' => 2.00,
            'portion' => 0.05,
        ]);

        $order = Order::create([
            'seller_id' => $this->user->id,
            'currency' => 'USD',
            'status' => Order::PENDING,
            'total_amount' => 0,
            'total_amount_usd' => 0,
            'money_returns' => 0,
        ]);

        $actionService = app(OrderActionService::class);

        // Línea 1: Hamburguesa sin cebolla
        $item1 = $actionService->addUpdateOrderItems($order, [
            'dish_id' => $dish->id,
            'quantity' => 1,
            'price_at_product' => 8.00,
            'price_usd_unit' => 8.00,
            'currency_at_order' => 'USD',
            'notes' => 'Sin cebolla',
            'modifiers' => [],
        ]);

        // Línea 2: Misma hamburguesa pero con Extra Tocineta (+2 USD)
        $item2 = $actionService->addUpdateOrderItems($order, [
            'dish_id' => $dish->id,
            'quantity' => 1,
            'price_at_product' => 10.00,
            'price_usd_unit' => 10.00,
            'currency_at_order' => 'USD',
            'notes' => 'Término medio',
            'modifiers' => [
                [
                    'modifier_option_id' => $optionExtraBacon->id,
                    'name' => 'Extra Tocineta',
                    'modifier_type' => 'add',
                    'extra_price_usd' => 2.00,
                ],
            ],
        ]);

        $this->assertNotEquals($item1->id, $item2->id);
        $this->assertEquals(2, $order->details()->where('dish_id', $dish->id)->count());
        $this->assertEquals(8.00, (float) $item1->price);
        $this->assertEquals(10.00, (float) $item2->price);

        $order->refresh();
        // 8.00 + 10.00 = 18.00
        $this->assertEquals(18.00, (float) $order->total_amount);
    }

    public function test_order_completion_deducts_stock_and_respects_add_remove_modifiers(): void
    {
        $ingredientCarne = Product::factory()->create([
            'name' => 'Carne 200g',
            'unit_cost' => 2.0,
            'presentation' => 1,
            'unit_of_measure' => 'und',
        ]);

        $ingredientQueso = Product::factory()->create([
            'name' => 'Queso Cheddar',
            'unit_cost' => 1.0,
            'presentation' => 1,
            'unit_of_measure' => 'und',
        ]);

        $ingredientTocineta = Product::factory()->create([
            'name' => 'Tocineta Extra',
            'unit_cost' => 1.5,
            'presentation' => 1,
            'unit_of_measure' => 'und',
        ]);

        // Crear lotes iniciales
        ProductLot::create([
            'product_id' => $ingredientCarne->id,
            'lot_number' => 'LOT-CARNE',
            'quantity' => 10,
            'unit_cost' => 2.0,
            'expiration_date' => now()->addDays(30),
        ]);

        ProductLot::create([
            'product_id' => $ingredientQueso->id,
            'lot_number' => 'LOT-QUESO',
            'quantity' => 10,
            'unit_cost' => 1.0,
            'expiration_date' => now()->addDays(30),
        ]);

        ProductLot::create([
            'product_id' => $ingredientTocineta->id,
            'lot_number' => 'LOT-TOCINETA',
            'quantity' => 10,
            'unit_cost' => 1.5,
            'expiration_date' => now()->addDays(30),
        ]);

        $dish = Dish::create([
            'name' => 'Burger Completa',
            'category_id' => $this->category->id,
            'designated_price' => 10.00,
            'cost_price' => 3.00,
            'suggested_price' => 8.00,
            'percentage_profit' => 1.5,
            'status' => 1,
            'iva' => false,
        ]);

        // Receta base: 1 Carne + 1 Queso
        $dish->ingredients()->attach($ingredientCarne->id, ['portion' => 1.0, 'designated_cost' => 2.0]);
        $dish->ingredients()->attach($ingredientQueso->id, ['portion' => 1.0, 'designated_cost' => 1.0]);

        $cashClosing = \App\Models\CashClosing::create([
            'seller_id' => $this->user->id,
            'status' => \App\Models\CashClosing::OPEN,
            'opening_date' => now(),
        ]);

        $order = Order::create([
            'seller_id' => $this->user->id,
            'cash_closing_id' => $cashClosing->id,
            'currency' => 'USD',
            'status' => Order::PENDING,
            'total_amount' => 0,
            'total_amount_usd' => 0,
            'money_returns' => 0,
        ]);

        $actionService = app(OrderActionService::class);

        // Crear plato con modificadores:
        // 1. REMOVE Queso (omisión, no se debe descontar queso)
        // 2. ADD Tocineta (adición, se debe descontar 1 tocineta)
        $actionService->addUpdateOrderItems($order, [
            'dish_id' => $dish->id,
            'quantity' => 2, // 2 platos
            'price_at_product' => 12.00,
            'price_usd_unit' => 12.00,
            'currency_at_order' => 'USD',
            'modifiers' => [
                [
                    'name' => 'Sin Queso',
                    'modifier_type' => 'remove',
                    'product_id' => $ingredientQueso->id,
                    'portion' => 0, // Remover todo
                ],
                [
                    'name' => 'Extra Tocineta',
                    'modifier_type' => 'add',
                    'product_id' => $ingredientTocineta->id,
                    'portion' => 1.0,
                    'extra_price_usd' => 2.00,
                ],
            ],
        ]);

        $order->refresh();

        // Completar orden
        $request = new \Illuminate\Http\Request();
        $request->merge([
            'payments' => [
                [
                    'method' => 'cash_usd',
                    'amount' => $order->total_amount,
                    'currency' => 'USD',
                ],
            ],
        ]);

        $actionService->complete($order, $request, $this->user->id);

        // Verificaciones de stock:
        // Carne: Tenía 10 - (2 platos * 1 carne) = 8
        $this->assertEquals(8, (float) ProductLot::where('product_id', $ingredientCarne->id)->sum('quantity'));

        // Queso: Tenía 10 y fue omitido (remove) => sigue en 10
        $this->assertEquals(10, (float) ProductLot::where('product_id', $ingredientQueso->id)->sum('quantity'));

        // Tocineta: Tenía 10 - (2 platos * 1 tocineta extra) = 8
        $this->assertEquals(8, (float) ProductLot::where('product_id', $ingredientTocineta->id)->sum('quantity'));
    }
}
