<?php

namespace Tests\Feature\Suppliers;

use App\Contracts\Suppliers\MafartaScraperServiceInterface;
use App\Models\Invoice;
use App\Models\Supplier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class MafartaScraperTest extends TestCase
{
    use RefreshDatabase;

    public function test_sync_mafarta_updates_indexed_and_control_number(): void
    {
        $user = \App\Models\User::create([
            'username' => 'admin_test',
            'email' => 'admin_test@test.com',
            'password_hash' => bcrypt('secret123'),
        ]);

        $supplier = Supplier::create([
            'id' => 23,
            'name' => 'C.A. MAFARTA',
            'rif' => 'J070012250',
            'dispatch_days' => ['monday', 'wednesday'],
            'order_days' => ['monday', 'tuesday'],
        ]);

        $invoice = Invoice::create([
            'supplier_id' => $supplier->id,
            'invoice_number' => '0005212608',
            'control_number' => null,
            'created_invoice_date' => '2026-08-01',
            'exp_date' => '2026-08-10',
            'payment_date' => '2026-08-10',
            'currency' => 'Bs',
            'is_indexed' => false,
            'total_amount' => 11029.52,
            'total_usd' => 14.73,
            'uploaded_by' => $user->id,
            'registered_by' => $user->id,
            'status' => 'pending',
            'status_payment' => 0,
        ]);

        Http::fake([
            'https://sic.drogueriascobeca.com/api/auth/login' => Http::response([
                'token' => 'fake-jwt-token',
                'user' => 'F31373',
                'client' => '31373',
                'drogueria' => '3',
            ], 200),
            'https://sic.drogueriascobeca.com/api/estadocuenta/consulta' => Http::response([
                'documentos' => [
                    [
                        'compania' => 'C.A. MAFARTA',
                        'tipoDoc' => 'FA',
                        'numDoc' => '0005212608',
                        'fechEmision' => '2026-08-03T00:00:00',
                        'fechVenc' => '2026-09-12T00:00:00',
                        'facturaDolari' => 1,
                        'nroControl' => '00-05309954',
                        'montoTotal' => 11029.52,
                        'montoTotal2' => 14.73,
                        'montoTasaConv' => 787.52,
                        'montoDifer' => 610.03,
                    ]
                ]
            ], 200),
        ]);

        $service = app(MafartaScraperServiceInterface::class);
        $result = $service->syncInvoices('F31373', 'Mafarta2026*', 23);

        $this->assertEquals(1, $result['updated']);

        $invoice->refresh();
        $this->assertTrue((bool) $invoice->is_indexed);
        $this->assertEquals('00-05309954', $invoice->control_number);
        $this->assertEquals('2026-09-12', $invoice->exp_date->format('Y-m-d'));
    }
}
