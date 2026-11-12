<?php

declare(strict_types=1);

namespace Tests\Feature\Restaurant;

use App\Models\Category;
use App\Models\Dish;
use App\Models\GeneralSetting;
use App\Models\Product;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DishCrudAndRecalculationTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected Category $category;
    protected Product $ingredient;

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
            'name' => 'Especialidades',
            'type' => 'dishes',
        ]);

        $this->ingredient = Product::factory()->create([
            'name' => 'Carne Angus',
            'unit_cost' => 4.00,
        ]);
    }

    public function test_can_create_dish_with_ingredients(): void
    {
        $payload = [
            'name' => 'Hamburguesa Angus Especial',
            'category_id' => $this->category->id,
            'percentage_profit' => 50,
            'cost_price' => 4.00,
            'suggested_price' => 6.00,
            'designated_price' => 6.50,
            'status' => 1,
            'ingredients' => [
                [
                    'product_id' => $this->ingredient->id,
                    'portion' => 1.0,
                    'designated_cost' => 4.00,
                ],
            ],
        ];

        $response = $this->postJson('/api/dishes', $payload);

        $response->assertCreated()
            ->assertJsonPath('data.name', 'Hamburguesa Angus Especial');

        $this->assertDatabaseHas('dishes', [
            'name' => 'Hamburguesa Angus Especial',
            'designated_price' => 6.50,
        ]);
    }

    public function test_can_list_and_show_dishes(): void
    {
        $dish = Dish::create([
            'name' => 'Hamburguesa Clásica',
            'category_id' => $this->category->id,
            'percentage_profit' => 40,
            'cost_price' => 3.00,
            'suggested_price' => 4.20,
            'designated_price' => 4.50,
            'status' => 1,
        ]);

        $listResponse = $this->getJson('/api/dishes');
        $listResponse->assertOk()
            ->assertJsonFragment(['name' => 'Hamburguesa Clásica']);

        $showResponse = $this->getJson("/api/dishes/{$dish->id}");
        $showResponse->assertOk()
            ->assertJsonPath('data.id', $dish->id)
            ->assertJsonPath('data.name', 'Hamburguesa Clásica');
    }

    public function test_can_update_and_destroy_dish(): void
    {
        $dish = Dish::create([
            'name' => 'Plato Temporal',
            'category_id' => $this->category->id,
            'percentage_profit' => 30,
            'cost_price' => 2.00,
            'suggested_price' => 2.60,
            'designated_price' => 3.00,
            'status' => 1,
        ]);

        $updatePayload = [
            'name' => 'Plato Temporal Modificado',
            'category_id' => $this->category->id,
            'percentage_profit' => 35,
            'cost_price' => 2.00,
            'suggested_price' => 2.70,
            'designated_price' => 3.50,
            'status' => 1,
            'ingredients' => [
                [
                    'product_id' => $this->ingredient->id,
                    'portion' => 0.5,
                    'designated_cost' => 2.00,
                ],
            ],
        ];

        $updateResponse = $this->putJson("/api/dishes/{$dish->id}", $updatePayload);
        $updateResponse->assertOk()
            ->assertJsonPath('data.name', 'Plato Temporal Modificado');

        $deleteResponse = $this->deleteJson("/api/dishes/{$dish->id}");
        $deleteResponse->assertOk()
            ->assertJsonFragment(['message' => 'Plato eliminado con éxito.']);

        $this->assertDatabaseMissing('dishes', [
            'id' => $dish->id,
            'deleted_at' => null,
        ]);
    }

    public function test_remove_fixed_costs_and_recalculate_endpoints(): void
    {
        $fixedCostProduct = Product::factory()->create([
            'name' => 'Costo Fijo Alquiler y Gas',
            'unit_cost' => 1.00,
        ]);

        $dish = Dish::create([
            'name' => 'Plato Con Costo Fijo',
            'category_id' => $this->category->id,
            'percentage_profit' => 50,
            'cost_price' => 5.00,
            'suggested_price' => 7.50,
            'designated_price' => 8.00,
            'status' => 1,
        ]);

        $dish->ingredients()->attach($this->ingredient->id, [
            'portion' => 1.0,
            'designated_cost' => 4.00,
        ]);

        $dish->ingredients()->attach($fixedCostProduct->id, [
            'portion' => 1.0,
            'designated_cost' => 1.00,
        ]);

        $response = $this->postJson('/api/dishes/remove-fixed-costs-recalculate');

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'recalculated_dishes_count',
                    'removed_ingredients_count',
                ],
            ]);

        $this->assertDatabaseMissing('dish_ingredients', [
            'dish_id' => $dish->id,
            'product_id' => $fixedCostProduct->id,
        ]);
    }
}
