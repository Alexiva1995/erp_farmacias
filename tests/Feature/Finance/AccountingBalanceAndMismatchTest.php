<?php

declare(strict_types=1);

namespace Tests\Feature\Finance;

use App\Models\CashClosing;
use App\Models\GeneralSetting;
use App\Models\Role;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AccountingBalanceAndMismatchTest extends TestCase
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

        GeneralSetting::updateOrCreate(
            ['id' => 1],
            [
                'app_name' => 'Farmacia Finanzas Test',
                'fiscal_mode' => 'demo',
                'default_currency' => 'USD',
            ]
        );
    }

    public function test_can_get_accounting_balance_general(): void
    {
        $response = $this->getJson('/api/finances/balance-general');

        $response->assertOk()
            ->assertJsonPath('status', 'success')
            ->assertJsonStructure([
                'data' => [
                    'assets' => [
                        'details',
                        'total_bruto',
                        'total_neto',
                        'depreciation',
                    ],
                    'liabilities' => [
                        'details',
                        'total',
                    ],
                    'equity',
                    'ratios' => [
                        'liquidity',
                        'solvency',
                    ],
                    'calculated_at',
                ],
            ]);
    }

    public function test_can_accept_cash_closure_mismatch_positive_and_negative(): void
    {
        $closing = CashClosing::create([
            'seller_id' => $this->user->id,
            'closing_date' => now()->toDateString(),
            'status' => 'open',
            'exchange_rate' => 36.50,
            'cop_exchange_rate' => 4000.00,
            'blind_mismatches' => '[]',
            'blind_note' => '',
        ]);

        // 1. Sobrante (diferencia positiva -> IN)
        $positivePayload = [
            'cash_closing_id' => $closing->id,
            'currency' => 'USD',
            'mismatch_type' => 'usd_cash',
            'difference' => 10.00,
        ];

        $posResponse = $this->postJson('/api/finances/cash-closure/mismatches/accept', $positivePayload);
        $posResponse->assertOk()
            ->assertJsonPath('status', 'success');

        $this->assertDatabaseHas('transactions', [
            'currency' => 'USD',
            'amount' => 10.00,
            'movement_type' => 'IN',
        ]);

        // 2. Faltante (diferencia negativa -> OUT)
        $negativePayload = [
            'cash_closing_id' => $closing->id,
            'currency' => 'USD',
            'mismatch_type' => 'usd_cash',
            'difference' => -5.00,
        ];

        $negResponse = $this->postJson('/api/finances/cash-closure/mismatches/accept', $negativePayload);
        $negResponse->assertOk()
            ->assertJsonPath('status', 'success');

        $this->assertDatabaseHas('transactions', [
            'currency' => 'USD',
            'amount' => 5.00,
            'movement_type' => 'OUT',
        ]);
    }
}
