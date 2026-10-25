<?php

namespace Tests\Feature\Restaurant;

use App\Enums\KitchenWasteReasonEnum;
use App\Enums\ProductionOrderStatusEnum;
use App\Models\Category;
use App\Models\Dish;
use App\Models\ProductLot;
use App\Models\Product;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class Phase4ProductionAndWasteTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected Product $ingredient;
    protected Product $subRecipeProduct;
    protected Dish $subRecipeDish;

    protected function setUp(): void
    {
        parent::setUp();

        $role = Role::firstOrCreate(['id' => 1], [
            'name' => 'admin',
            'description' => 'Administrator',
        ]);

        $this->user = User::factory()->create([
            'role_id' => $role->id,
            'is_active' => true,
        ]);

        $category = Category::create([
            'name' => 'Insumos de Cocina',
            'status' => 'active',
        ]);

        // Materia prima con merma del 10% (yield = 90%)
        $this->ingredient = Product::create([
            'name' => 'Tomate Perita Maduro',
            'unit_cost' => 2.00,
            'sale_price' => 3.00,
            'yield_percentage' => 90.00,
            'stock' => 20.00,
            'category_id' => $category->id,
            'is_active' => true,
        ]);

        // Lote de materia prima
        ProductLot::create([
            'product_id' => $this->ingredient->id,
            'lot_number' => 'LOT-TOM-01',
            'quantity' => 20.00,
            'unit_cost' => 2.00,
            'expiration_date' => now()->addDays(15)->toDateString(),
        ]);

        // Producto elaborado resultante
        $this->subRecipeProduct = Product::create([
            'name' => 'Salsa Pomodoro Concentrada (L)',
            'unit_cost' => 3.00,
            'sale_price' => 4.50,
            'is_sub_recipe' => true,
            'stock' => 0.00,
            'category_id' => $category->id,
            'is_active' => true,
        ]);

        // Sub-receta
        $this->subRecipeDish = Dish::create([
            'name' => 'Sub-receta Pomodoro',
            'cost_price' => 3.00,
            'cpv' => 3.00,
            'suggested_price' => 4.00,
            'designated_price' => 4.00,
            'percentage_profit' => 30.00,
            'status' => 3,
            'output_product_id' => $this->subRecipeProduct->id,
            'expected_yield_quantity' => 4.00,
            'shelf_life_days' => 10,
        ]);

        $this->subRecipeDish->ingredients()->attach($this->ingredient->id, [
            'portion' => 4.50,
            'designated_cost' => 9.00,
        ]);
    }

    public function test_can_create_and_execute_production_order()
    {
        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/restaurant/production/orders', [
                'dish_id' => $this->subRecipeDish->id,
                'batch_multiplier' => 2.0,
                'actual_quantity' => 8.0,
                'notes' => 'Tanda doble matutina',
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('order.dish.id', $this->subRecipeDish->id)
            ->assertJsonPath('order.actual_quantity', fn ($val) => (float) $val === 8.0)
            ->assertJsonPath('order.status', ProductionOrderStatusEnum::COMPLETED->value);

        // Se aumentó el stock del producto resultante
        $this->subRecipeProduct->refresh();
        $this->assertEquals(8.0, (float) $this->subRecipeProduct->stock);

        // Se creó el lote nuevo para el producto elaborado
        $this->assertDatabaseHas('product_lots', [
            'product_id' => $this->subRecipeProduct->id,
            'quantity' => 8.0,
        ]);

        // Se descontaron ingredientes aplicando yield
        $this->ingredient->refresh();
        $this->assertLessThan(20.00, (float) $this->ingredient->stock);
    }

    public function test_can_record_kitchen_waste()
    {
        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/restaurant/waste/records', [
                'item_type' => 'product',
                'product_id' => $this->ingredient->id,
                'quantity' => 2.50,
                'reason' => KitchenWasteReasonEnum::BURNED_SPOILED->value,
                'notes' => 'Tomates sobremaduros',
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('waste.item_type', 'product')
            ->assertJsonPath('waste.quantity', fn ($val) => (float) $val === 2.5)
            ->assertJsonPath('waste.total_loss_usd', fn ($val) => (float) $val === 5.0);

        $this->ingredient->refresh();
        $this->assertEquals(17.50, (float) $this->ingredient->stock);
    }
}
