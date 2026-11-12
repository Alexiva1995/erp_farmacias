<?php

declare(strict_types=1);

namespace Tests\Feature\Fiscal;

use App\Models\FiscalHistory;
use App\Models\FiscalZReport;
use App\Models\Order;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FiscalZReportAndSpoolerTest extends TestCase
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

    public function test_can_list_and_filter_fiscal_z_reports(): void
    {
        FiscalZReport::create([
            'report_number' => 101,
            'report_date' => '2026-11-09',
            'opening_time' => '08:00:00',
            'closing_time' => '21:00:00',
            'first_invoice_number' => '00001',
            'last_invoice_number' => '00025',
            'invoices_count' => 25,
            'exempt_amount' => 150.00,
            'base_16_amount' => 500.00,
            'iva_amount' => 80.00,
            'igtf_base_amount' => 200.00,
            'igtf_amount' => 6.00,
            'total_amount' => 736.00,
            'status' => 'closed',
        ]);

        $response = $this->getJson('/api/fiscal/z-reports?start_date=2026-11-01&end_date=2026-11-11');

        $response->assertOk()
            ->assertJsonStructure([
                'data',
                'total',
                'summary',
            ])
            ->assertJsonFragment(['report_number' => 101]);
    }

    public function test_can_show_fiscal_z_report_details(): void
    {
        $report = FiscalZReport::create([
            'report_number' => 102,
            'report_date' => '2026-11-10',
            'opening_time' => '08:00:00',
            'closing_time' => '22:00:00',
            'first_invoice_number' => '00026',
            'last_invoice_number' => '00050',
            'invoices_count' => 25,
            'exempt_amount' => 200.00,
            'base_16_amount' => 600.00,
            'iva_amount' => 96.00,
            'igtf_base_amount' => 300.00,
            'igtf_amount' => 9.00,
            'total_amount' => 905.00,
            'status' => 'closed',
        ]);

        $response = $this->getJson("/api/fiscal/z-reports/{$report->id}");

        $response->assertOk()
            ->assertJsonPath('data.id', $report->id)
            ->assertJsonPath('data.report_number', 102);
    }

    public function test_can_generate_fiscal_z_report_for_date(): void
    {
        $payload = [
            'date' => '2026-11-11',
            'force' => true,
        ];

        $response = $this->postJson('/api/fiscal/z-reports/generate', $payload);

        $response->assertOk()
            ->assertJsonStructure([
                'message',
                'data' => [
                    'id',
                    'report_number',
                    'report_date',
                ],
            ]);

        $this->assertDatabaseHas('fiscal_z_reports', [
            'report_date' => '2026-11-11',
        ]);
    }

    public function test_can_manage_fiscal_commands_and_confirm_invoice(): void
    {
        // 1. Probar encolado y consulta de comandos fiscales
        $cmdResponse = $this->postJson('/api/fiscal/commands', [
            'command' => 'REPORT_X',
            'payload' => ['printer' => 'bixolon'],
        ]);

        $cmdResponse->assertCreated()
            ->assertJsonPath('data.command', 'REPORT_X');

        $pendingCmdResponse = $this->getJson('/api/fiscal/commands/pending');
        $pendingCmdResponse->assertOk()
            ->assertJsonPath('data.command', 'REPORT_X');

        $cmdId = $pendingCmdResponse->json('data.id');

        $confirmCmdResponse = $this->patchJson("/api/fiscal/commands/{$cmdId}/confirm", [
            'status' => 'success',
        ]);
        $confirmCmdResponse->assertOk();

        // 2. Probar confirmación y lookup de facturas impresas
        $order = Order::create([
            'seller_id' => $this->user->id,
            'client_id' => null,
            'total_amount' => 116.00,
            'total_amount_usd' => 116.00,
            'money_returns' => 0.00,
            'status' => 'Pending',
            'order_date' => now()->toDateString(),
            'currency' => 'USD',
        ]);

        $fiscal = FiscalHistory::create([
            'user_id' => $this->user->id,
            'order_id' => $order->id,
            'business_name' => 'Farmacia Test C.A.',
            'identification' => 'J-12345678-9',
            'invoice_date' => now()->toDateString(),
            'is_queued' => true,
            'invoice_number' => null,
            'taxable_amount' => 100.00,
            'iva_amount' => 16.00,
            'total_amount' => 116.00,
            'status' => 'pending',
        ]);

        $confirmResponse = $this->patchJson("/api/fiscal/confirm/{$fiscal->id}", [
            'invoice_number' => '00004567',
        ]);

        $confirmResponse->assertOk()
            ->assertJsonPath('invoice_number', '00004567');

        $this->assertDatabaseHas('fiscal_history', [
            'id' => $fiscal->id,
            'invoice_number' => '00004567',
            'is_queued' => 0,
        ]);

        $lookupResponse = $this->getJson('/api/fiscal/invoices/lookup?invoice_number=00004567');
        $lookupResponse->assertOk()
            ->assertJsonPath('found', true)
            ->assertJsonPath('data.invoice_number', '00004567');
    }
}
