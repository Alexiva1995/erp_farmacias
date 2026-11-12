<?php

declare(strict_types=1);

namespace Tests\Feature\Inventory;

use App\Enums\CategoryTypeEnum;
use App\Models\Category;
use App\Models\GroupsLaboratory;
use App\Models\Laboratory;
use App\Models\Product;
use App\Models\ProductLot;
use App\Models\Role;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CategoryAndLaboratoryManagementTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $role = Role::firstOrCreate(['id' => 1], [
            'name' => 'Admin',
            'guard_name' => 'web',
            'menu_permissions' => ['*'],
        ]);

        $this->user = User::factory()->create(['role_id' => $role->id]);
        $this->actingAs($this->user, 'sanctum');
    }

    public function test_can_manage_categories_lifecycle_and_detach_products(): void
    {
        // 1. Crear categoría
        $createPayload = [
            'name' => 'Analgésicos y Antipiréticos',
            'type' => CategoryTypeEnum::INVENTORY->value,
        ];

        $createResponse = $this->postJson('/api/inventory/categories-manage', $createPayload);
        $createResponse->assertOk()
            ->assertJsonPath('message', 'Categoría guardada correctamente.')
            ->assertJsonPath('category.name', 'Analgésicos y Antipiréticos');

        $categoryId = (int) $createResponse->json('category.id');

        // 2. Asociar un producto a la categoría
        $product = Product::create([
            'name' => 'Acetaminofén 500mg',
            'category_id' => $categoryId,
            'unit_cost' => 1.00,
            'sale_price' => 1.80,
            'stock' => 50,
        ]);

        // 3. Listar categorías y comprobar conteo
        $listResponse = $this->getJson('/api/inventory/categories-manage');
        $listResponse->assertOk();

        $items = collect($listResponse->json('data'));
        $catItem = $items->firstWhere('id', $categoryId);
        $this->assertNotNull($catItem);
        $this->assertEquals(1, $catItem['products_count']);

        // 4. Eliminar categoría y verificar desvinculación
        $deleteResponse = $this->deleteJson("/api/inventory/categories-manage/{$categoryId}");
        $deleteResponse->assertOk()
            ->assertJsonPath('message', 'Categoría eliminada y elementos desvinculados correctamente.');

        $this->assertDatabaseMissing('categories', ['id' => $categoryId]);
        $this->assertNull($product->fresh()->category_id);
    }

    public function test_can_manage_laboratories_and_groups_with_product_stock_counts(): void
    {
        // 1. Crear Grupo de Laboratorios
        $groupPayload = [
            'name' => 'Grupo Farmacéutico Nacional',
        ];

        $groupResponse = $this->postJson('/api/inventory/laboratories-manage/groups', $groupPayload);
        $groupResponse->assertOk()
            ->assertJsonPath('message', 'Grupo guardado y laboratorios asignados')
            ->assertJsonPath('group.name', 'Grupo Farmacéutico Nacional');

        $groupId = (int) $groupResponse->json('group.id');

        // 2. Crear Laboratorio asignado al grupo
        $labPayload = [
            'name' => 'Laboratorios Behrens',
            'group_id' => $groupId,
        ];

        $labResponse = $this->postJson('/api/inventory/laboratories-manage', $labPayload);
        $labResponse->assertOk()
            ->assertJsonPath('message', 'Laboratorio guardado correctamente')
            ->assertJsonPath('laboratory.name', 'Laboratorios Behrens');

        $labId = (int) $labResponse->json('laboratory.id');

        // 3. Crear Producto vinculado al laboratorio con Lote
        $product = Product::create([
            'name' => 'Solución Fisiológica 0.9%',
            'laboratory_id' => $labId,
            'unit_cost' => 2.50,
            'sale_price' => 3.80,
            'stock' => 100,
        ]);

        ProductLot::create([
            'product_id' => $product->id,
            'lot_number' => 'LOT-BEH-2026',
            'expiration_date' => Carbon::now()->addYear()->format('Y-m-d'),
            'quantity' => 100,
            'unit_cost' => 2.50,
            'location' => 'ALMACEN-A',
        ]);

        // 4. Consultar listado de laboratorios
        $listResponse = $this->getJson('/api/inventory/laboratories-manage');
        $listResponse->assertOk();

        $labsData = collect($listResponse->json('data'));
        $labItem = $labsData->firstWhere('id', $labId);
        $this->assertNotNull($labItem);
        $this->assertEquals(1, $labItem['products_count']);
        $this->assertEquals(100, (int) $labItem['units_count']);

        // 5. Consultar listado de grupos
        $groupsListResponse = $this->getJson('/api/inventory/laboratories-manage/groups');
        $groupsListResponse->assertOk();

        $groupsData = collect($groupsListResponse->json());
        $grpItem = $groupsData->firstWhere('id', $groupId);
        $this->assertNotNull($grpItem);
        $this->assertEquals(1, $grpItem['labs_count']);
        $this->assertEquals(1, $grpItem['products_count']);

        // 6. Eliminar laboratorio y verificar desvinculación de productos
        $deleteLabResponse = $this->deleteJson("/api/inventory/laboratories-manage/{$labId}");
        $deleteLabResponse->assertOk()
            ->assertJsonPath('message', 'Laboratorio eliminado y productos desvinculados');

        $this->assertDatabaseMissing('laboratories', ['id' => $labId]);
        $this->assertNull($product->fresh()->laboratory_id);

        // 7. Eliminar grupo
        $deleteGroupResponse = $this->deleteJson("/api/inventory/laboratories-manage/groups/{$groupId}");
        $deleteGroupResponse->assertOk()
            ->assertJsonPath('message', 'Grupo eliminado correctamente');

        $this->assertDatabaseMissing('groups_laboratories', ['id' => $groupId]);
    }
}
