<?php

declare(strict_types=1);

namespace Tests\Feature\Central;

use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TenantAndBranchManagementTest extends TestCase
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
        $this->actingAs($this->user);
    }

    public function test_can_list_tenants_and_domains(): void
    {
        $tenant = Tenant::create([
            'id' => 'farmacia_central',
            'company_name' => 'Farmacia Central Matriz',
            'branch_code' => 'MATRIZ',
            'is_branch_active' => true,
        ]);

        $tenant->domains()->create([
            'domain' => 'farmacia_central.tovaerp.com',
        ]);

        $response = $this->getJson('/api/central/tenants');

        $response->assertOk()
            ->assertJsonStructure(['data']);

        $data = $response->json('data');
        $this->assertNotEmpty($data);
        $ids = collect($data)->pluck('id')->toArray();
        $this->assertContains('farmacia_central', $ids);
    }

    public function test_can_fetch_multi_tenant_supplier_audit(): void
    {
        Tenant::create([
            'id' => 'farmacia_audit_test',
            'company_name' => 'Farmacia Para Auditoria',
            'branch_code' => 'AUDIT-01',
        ]);

        $response = $this->getJson('/api/central/suppliers-audit?supplier_id=1016');

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'period',
                    'supplier',
                    'kpis',
                    'tenants',
                    'suppliers',
                ],
            ]);
    }

    public function test_can_list_group_branches_for_parent_tenant(): void
    {
        $parent = Tenant::create([
            'id' => 'matriz_corp',
            'company_name' => 'Corporativo Farmacéutico',
            'branch_code' => 'MATRIZ',
            'can_create_branches' => true,
            'max_branches' => 10,
        ]);

        $branch = Tenant::create([
            'id' => 'sucursal_este',
            'parent_tenant_id' => 'matriz_corp',
            'company_name' => 'Sucursal Este',
            'branch_code' => 'SUC-02',
            'is_branch_active' => true,
        ]);

        $response = $this->getJson('/api/branches?tenant_id=matriz_corp');

        $response->assertOk()
            ->assertJsonStructure(['data']);

        $data = $response->json('data');
        $this->assertCount(2, $data);

        $branchCodes = collect($data)->pluck('branch_code')->toArray();
        $this->assertContains('MATRIZ', $branchCodes);
        $this->assertContains('SUC-02', $branchCodes);
    }
}
