<?php

declare(strict_types=1);

namespace Tests\Feature\Operations;

use App\Models\ExpiredLog;
use App\Models\GeneralSetting;
use App\Models\Loan;
use App\Models\Product;
use App\Models\ProductLot;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LoanAndDonationManagementTest extends TestCase
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
                'app_name' => 'Farmacia Operaciones Test',
                'fiscal_mode' => 'demo',
                'default_currency' => 'USD',
            ]
        );
    }

    public function test_can_create_and_list_loans(): void
    {
        $payload = [
            'loan_date' => now()->toDateString(),
            'monthly_payment' => 250.00,
            'total_installments' => 24,
        ];

        $response = $this->postJson('/api/loans', $payload);

        $response->assertCreated()
            ->assertJsonPath('message', 'Préstamo creado con éxito.');

        $this->assertEquals(250.0, (float) $response->json('loan.monthly_payment'));

        $this->assertDatabaseHas('loans', [
            'monthly_payment' => 250.00,
            'total_installments' => 24,
        ]);

        $listResponse = $this->getJson('/api/loans');
        $listResponse->assertOk()
            ->assertJsonStructure(['data', 'total']);
    }

    public function test_can_update_and_delete_loan(): void
    {
        $loan = Loan::create([
            'loan_date' => now()->toDateString(),
            'monthly_payment' => 300.00,
            'total_installments' => 12,
        ]);

        $updateResponse = $this->putJson("/api/loans/{$loan->id}", [
            'loan_date' => now()->toDateString(),
            'monthly_payment' => 350.00,
            'total_installments' => 10,
        ]);

        $updateResponse->assertOk();
        $this->assertEquals(350.0, (float) $updateResponse->json('loan.monthly_payment'));

        $deleteResponse = $this->deleteJson("/api/loans/{$loan->id}");
        $deleteResponse->assertNoContent();

        $this->assertDatabaseMissing('loans', [
            'id' => $loan->id,
        ]);
    }

    public function test_can_register_payment_for_loan(): void
    {
        $loan = Loan::create([
            'loan_date' => now()->toDateString(),
            'monthly_payment' => 500.00,
            'total_installments' => 6,
        ]);

        $payload = [
            'amount' => 500.00,
            'payment_date' => now()->toDateString(),
            'account' => 'Banesco Panama USD',
        ];

        $response = $this->postJson("/api/loans/{$loan->id}/payments", $payload);

        $response->assertOk()
            ->assertJsonPath('message', 'Abono registrado con éxito.')
            ->assertJsonStructure(['expense', 'loan_remaining_balance']);
    }

    public function test_can_get_loans_total_balance(): void
    {
        Loan::create([
            'loan_date' => now()->toDateString(),
            'monthly_payment' => 200.00,
            'total_installments' => 5,
        ]);

        $response = $this->getJson('/api/loans/balance');

        $response->assertOk()
            ->assertJsonPath('data.currency', 'USD')
            ->assertJsonStructure([
                'data' => [
                    'total_balance',
                    'currency',
                    'calculated_at',
                ],
            ]);
    }

    public function test_can_record_donation_for_expired_products(): void
    {
        $product = Product::factory()->create([
            'name' => 'Amoxicilina 500mg',
            'unit_cost' => 2.50,
        ]);

        $lot = ProductLot::create([
            'product_id' => $product->id,
            'lot_number' => 'LOTE-TEST-001',
            'expiration_date' => now()->subMonth()->toDateString(),
            'quantity' => 20,
            'cost_price' => 2.50,
        ]);

        $expiredLog = ExpiredLog::create([
            'lot_id' => $lot->id,
            'product_id' => $product->id,
            'product_name' => $product->name,
            'lot_number' => $lot->lot_number,
            'expired_quantity' => 10,
            'cost_per_unit' => 2.50,
            'total_lost_value' => 25.00,
        ]);

        $payload = [
            'institution_name' => 'Hospital Central San Cristóbal',
            'expired_log_ids' => [$expiredLog->id],
        ];

        $response = $this->postJson('/api/donations', $payload);

        $response->assertCreated()
            ->assertJsonPath('message', 'Donación registrada con éxito.');

        $this->assertDatabaseHas('donations', [
            'institution_name' => 'Hospital Central San Cristóbal',
        ]);

        $this->assertDatabaseHas('donative_logs', [
            'expired_log_id' => $expiredLog->id,
        ]);
    }
}
