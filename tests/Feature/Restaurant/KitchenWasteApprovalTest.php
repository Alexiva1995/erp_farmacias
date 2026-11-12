<?php

declare(strict_types=1);

namespace Tests\Feature\Restaurant;

use App\Enums\KitchenWasteReasonEnum;
use App\Enums\KitchenWasteStatusEnum;
use App\Models\Dish;
use App\Models\KitchenWaste;
use App\Models\Product;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class KitchenWasteApprovalTest extends TestCase
{
    use RefreshDatabase;

    protected User $adminUser;
    protected User $employeeUser;
    protected Product $product;

    protected function setUp(): void
    {
        parent::setUp();

        $adminRole = Role::firstOrCreate(['id' => 1], [
            'name' => 'Admin',
            'guard_name' => 'web',
            'menu_permissions' => ['*'],
        ]);

        $employeeRole = Role::firstOrCreate(['id' => 5], [
            'name' => 'Cocinero',
            'guard_name' => 'web',
            'menu_permissions' => ['kitchen.*'],
        ]);

        $this->adminUser = User::factory()->create(['role_id' => $adminRole->id]);
        $this->employeeUser = User::factory()->create(['role_id' => $employeeRole->id]);

        $this->product = Product::factory()->create([
            'name' => 'Tomate Fresco',
            'unit_cost' => 1.50,
            'stock' => 50,
        ]);
    }

    public function test_employee_can_report_waste_which_stays_pending(): void
    {
        $this->actingAs($this->employeeUser);

        $payload = [
            'item_type' => 'product',
            'product_id' => $this->product->id,
            'quantity' => 5,
            'reason' => 'burned_spoiled',
            'notes' => 'Tomates sobrecocinados por exceso de fuego',
        ];

        $response = $this->postJson('/api/restaurant/waste/records', $payload);

        $response->assertCreated()
            ->assertJsonPath('waste.status', 'pending');

        $this->assertDatabaseHas('kitchen_wastes', [
            'product_id' => $this->product->id,
            'quantity' => 5,
            'status' => 'pending',
            'user_id' => $this->employeeUser->id,
        ]);
    }

    public function test_can_list_kitchen_waste_records(): void
    {
        $this->actingAs($this->adminUser);

        KitchenWaste::create([
            'user_id' => $this->adminUser->id,
            'item_type' => 'product',
            'product_id' => $this->product->id,
            'quantity' => 2,
            'unit_cost' => 1.50,
            'total_loss_usd' => 3.00,
            'reason' => 'expired',
            'notes' => 'Vencido en almacén',
            'status' => 'approved',
            'approved_by' => $this->adminUser->id,
            'approved_at' => now(),
            'occurred_at' => now(),
        ]);

        $response = $this->getJson('/api/restaurant/waste/records');

        $response->assertOk()
            ->assertJsonFragment(['notes' => 'Vencido en almacén']);
    }

    public function test_admin_can_approve_pending_waste(): void
    {
        $waste = KitchenWaste::create([
            'user_id' => $this->employeeUser->id,
            'item_type' => 'product',
            'product_id' => $this->product->id,
            'quantity' => 4,
            'unit_cost' => 1.50,
            'total_loss_usd' => 6.00,
            'reason' => 'burned_spoiled',
            'notes' => 'Esperando aprobación',
            'status' => 'pending',
            'occurred_at' => now(),
        ]);

        // Intentar con usuario no autorizado (debe retornar 403)
        $this->actingAs($this->employeeUser);
        $forbiddenResponse = $this->postJson("/api/restaurant/waste/records/{$waste->id}/approve");
        $forbiddenResponse->assertForbidden();

        // Aprobar con admin
        $this->actingAs($this->adminUser);
        $approveResponse = $this->postJson("/api/restaurant/waste/records/{$waste->id}/approve");
        $approveResponse->assertOk()
            ->assertJsonPath('waste.status', 'approved');

        $this->assertDatabaseHas('kitchen_wastes', [
            'id' => $waste->id,
            'status' => 'approved',
            'approved_by' => $this->adminUser->id,
        ]);
    }

    public function test_admin_can_reject_pending_waste(): void
    {
        $waste = KitchenWaste::create([
            'user_id' => $this->employeeUser->id,
            'item_type' => 'product',
            'product_id' => $this->product->id,
            'quantity' => 3,
            'unit_cost' => 1.50,
            'total_loss_usd' => 4.50,
            'reason' => 'dropped',
            'notes' => 'Se cayó accidentalmente',
            'status' => 'pending',
            'occurred_at' => now(),
        ]);

        $this->actingAs($this->adminUser);
        $rejectResponse = $this->postJson("/api/restaurant/waste/records/{$waste->id}/reject", [
            'rejection_reason' => 'Falta evidencia fotográfica obligatoria según protocolo.',
        ]);

        $rejectResponse->assertOk()
            ->assertJsonPath('waste.status', 'rejected');

        $this->assertDatabaseHas('kitchen_wastes', [
            'id' => $waste->id,
            'status' => 'rejected',
        ]);
    }
}
