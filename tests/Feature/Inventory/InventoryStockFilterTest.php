<?php

declare(strict_types=1);

namespace Tests\Feature\Inventory;

use App\Models\Category;
use App\Models\GroupsProduct;
use App\Models\Laboratory;
use App\Models\Product;
use App\Models\ProductLot;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InventoryStockFilterTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Laboratory $labA;
    private Laboratory $labB;
    private Product $prod1;
    private Product $prod2;
    private Product $prod3;

    protected function setUp(): void
    {
        parent::setUp();

        $role = Role::firstOrCreate(['id' => 1], [
            'name' => 'Admin',
            'guard_name' => 'web',
            'menu_permissions' => ['*'],
        ]);

        $this->user = User::factory()->create([
            'role_id' => $role->id,
            'is_active' => true,
        ]);

        $this->actingAs($this->user);

        $this->labA = Laboratory::create(['name' => 'Laboratorio Bayer']);
        $this->labB = Laboratory::create(['name' => 'Laboratorio Pfizer']);

        $category = Category::create(['name' => 'Farmacia', 'type' => 'inventory']);

        // Producto 1 con stock en lotes
        $this->prod1 = Product::create([
            'name' => 'Acetaminofen 500mg',
            'barcode' => '770111111111',
            'unit_cost' => 1.50,
            'sale_price' => 3.00,
            'laboratory_id' => $this->labA->id,
            'category_id' => $category->id,
            'is_active' => true,
            'is_deleted' => false,
        ]);

        ProductLot::create([
            'product_id' => $this->prod1->id,
            'lot_number' => 'LOT-001',
            'quantity' => 50,
            'unit_cost' => 1.50,
            'expiration_date' => now()->addYear()->format('Y-m-d'),
        ]);

        // Producto 2 sin stock
        $this->prod2 = Product::create([
            'name' => 'Ibuprofeno 400mg',
            'barcode' => '770222222222',
            'unit_cost' => 2.00,
            'sale_price' => 4.00,
            'laboratory_id' => $this->labA->id,
            'category_id' => $category->id,
            'is_active' => true,
            'is_deleted' => false,
        ]);

        // Producto 3 en otro laboratorio y con stock
        $this->prod3 = Product::create([
            'name' => 'Loratadina 10mg',
            'barcode' => '770333333333',
            'unit_cost' => 0.80,
            'sale_price' => 1.80,
            'laboratory_id' => $this->labB->id,
            'category_id' => $category->id,
            'is_active' => true,
            'is_deleted' => false,
        ]);

        ProductLot::create([
            'product_id' => $this->prod3->id,
            'lot_number' => 'LOT-002',
            'quantity' => 20,
            'unit_cost' => 0.80,
            'expiration_date' => now()->addMonths(6)->format('Y-m-d'),
        ]);
    }

    public function test_can_filter_stock_individual_with_pagination(): void
    {
        $payload = [
            'viewType' => 'individual',
            'page' => 1,
            'itemsPerPage' => 10,
        ];

        $response = $this->postJson('/api/inventory/stock/filter', $payload);

        $response->assertStatus(200);
        $response->assertJson([
            'status' => 'success',
        ]);
        $response->assertJsonStructure([
            'status',
            'data' => [
                'data' => [
                    '*' => [
                        'id',
                        'name',
                        'unit_cost',
                        'lote_quantity',
                        'laboratory',
                    ],
                ],
                'total',
                'current_page',
            ],
        ]);

        $this->assertGreaterThanOrEqual(3, $response->json('data.total'));
    }

    public function test_can_filter_stock_by_search_query(): void
    {
        $payload = [
            'q' => 'Acetaminofen',
            'viewType' => 'individual',
        ];

        $response = $this->postJson('/api/inventory/stock/filter', $payload);

        $response->assertStatus(200);
        $items = $response->json('data.data');

        $this->assertCount(1, $items);
        $this->assertEquals($this->prod1->id, $items[0]['id']);
    }

    public function test_can_filter_stock_by_laboratory(): void
    {
        $payload = [
            'laboratoryId' => $this->labB->id,
            'viewType' => 'individual',
        ];

        $response = $this->postJson('/api/inventory/stock/filter', $payload);

        $response->assertStatus(200);
        $items = $response->json('data.data');

        $this->assertCount(1, $items);
        $this->assertEquals($this->prod3->id, $items[0]['id']);
    }

    public function test_can_filter_stock_by_stock_availability(): void
    {
        $payload = [
            'hasStock' => 1,
            'viewType' => 'individual',
        ];

        $response = $this->postJson('/api/inventory/stock/filter', $payload);

        $response->assertStatus(200);
        $items = $response->json('data.data');

        $productIds = collect($items)->pluck('id')->toArray();
        $this->assertContains($this->prod1->id, $productIds);
        $this->assertContains($this->prod3->id, $productIds);
        $this->assertNotContains($this->prod2->id, $productIds);
    }

    public function test_can_filter_stock_without_pagination(): void
    {
        $payload = [
            'viewType' => 'individual',
        ];

        $response = $this->postJson('/api/inventory/stock/filter-without-paginate', $payload);

        $response->assertStatus(200);
        $data = $response->json('data');

        $this->assertIsArray($data);
        $this->assertGreaterThanOrEqual(3, count($data));
    }

    public function test_can_filter_stock_grouped_by_product_group(): void
    {
        $group = GroupsProduct::create(['name' => 'Analgésicos Grupo']);
        $this->prod1->update(['group_id' => $group->id]);
        $this->prod2->update(['group_id' => $group->id]);

        $payload = [
            'viewType' => 'group',
            'page' => 1,
            'itemsPerPage' => 10,
        ];

        $response = $this->postJson('/api/inventory/stock/filter', $payload);

        $response->assertStatus(200);
        $items = $response->json('data.data');

        $this->assertNotEmpty($items);
        $groupItem = collect($items)->firstWhere('group_id', $group->id);
        $this->assertNotNull($groupItem);
        $this->assertNotEmpty($groupItem['productos']);
        $this->assertCount(2, $groupItem['productos']);
    }
}
