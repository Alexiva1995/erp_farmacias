<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Contracts\Repositories\FiscalZReportRepositoryInterface;
use App\Models\FiscalCommand;
use App\Models\FiscalHistory;
use App\Models\FiscalZReport;
use App\Models\Order;
use App\Models\Role;
use App\Models\SalesBook;
use App\Models\SalesBookEntry;
use App\Models\User;
use App\Services\Fiscal\FiscalZReportService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class FiscalZReportServiceTest extends TestCase
{
    use RefreshDatabase;

    private FiscalZReportService $service;
    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(FiscalZReportService::class);

        $role = Role::firstOrCreate(
            ['id' => 1],
            ['name' => 'Admin', 'guard_name' => 'web', 'menu_permissions' => ['*']]
        );
        $this->user = User::factory()->create(['role_id' => $role->id]);
    }

    public function test_closes_past_and_executed_reports_and_syncs_today_invoices(): void
    {
        $yesterday = Carbon::yesterday()->format('Y-m-d');
        $today = Carbon::today()->format('Y-m-d');

        $pastReport = FiscalZReport::create([
            'report_number' => 700,
            'report_date' => $yesterday,
            'opening_time' => '08:00:00',
            'closing_time' => '21:00:00',
            'invoices_count' => 1,
            'total_amount' => 100,
            'status' => 'open',
        ]);

        FiscalCommand::create([
            'command' => 'REPORT_Z',
            'payload' => ['target_date' => $today],
            'status' => 'success',
        ]);

        $order = Order::create([
            'seller_id' => $this->user->id,
            'total_amount' => 119.00,
            'total_amount_usd' => 3.00,
            'money_returns' => 0,
            'status' => Order::COMPLETED,
            'order_date' => now(),
            'currency' => 'BS',
        ]);

        FiscalHistory::create([
            'user_id' => $this->user->id,
            'order_id' => $order->id,
            'business_name' => 'Cliente Hoy',
            'identification' => 'V-11111111',
            'invoice_date' => $today,
            'invoice_number' => '00000801',
            'exempt_amount' => 10.00,
            'taxable_amount' => 91.38,
            'iva_amount' => 14.62,
            'spe' => true,
            'spe_surcharge_amount' => 3.00,
            'total_amount' => 119.00,
            'is_queued' => false,
        ]);

        $paginated = $this->service->getFilteredPaginated([], 10);

        $this->assertEquals('closed', $pastReport->fresh()->status);
        $this->assertGreaterThanOrEqual(2, $paginated->total());

        $todayReport = app(FiscalZReportRepositoryInterface::class)->findByDate($today);
        $this->assertNotNull($todayReport);
        $this->assertEquals('closed', $todayReport->status); // cerrado por comando REPORT_Z exitoso
        $this->assertEquals('00000801', $todayReport->first_invoice_number);
        $this->assertEquals(119.00, (float) $todayReport->total_amount);
    }

    public function test_open_next_generate_month_find_stats_and_delete(): void
    {
        $report1 = $this->service->openNextReport('2026-02-01', 800);
        $this->assertEquals(800, $report1->report_number);
        // Llamada idempotente retorna existente
        $this->assertSame($report1->id, $this->service->openNextReport('2026-02-01', 999)->id);

        $report2 = $this->service->openNextReport('2026-02-02');
        $this->assertEquals(801, $report2->report_number);

        $this->assertSame($report2->id, $this->service->findById($report2->id)?->id);

        $stats = $this->service->getSummaryStats([]);
        $this->assertIsArray($stats);

        $monthBackward = $this->service->generateForMonth(2026, 1, 750, true, true, true);
        $this->assertCount(31, $monthBackward);
        $this->assertEquals(720, $monthBackward->first()->report_number);
        $this->assertEquals(750, $monthBackward->last()->report_number);

        $monthForward = $this->service->generateForMonth(2025, 12, 600, true, false, true);
        $this->assertCount(31, $monthForward);
        $this->assertEquals(600, $monthForward->first()->report_number);

        // Mes futuro debe retornar colección vacía
        $futureMonth = $this->service->generateForMonth(2099, 1, 900, true, true, false);
        $this->assertCount(0, $futureMonth);

        $this->assertTrue($this->service->deleteByNumber(800));
        $this->assertTrue($this->service->deleteByDate('2026-02-02'));
    }

    public function test_verifies_image_with_ai_creates_zip_and_updates_range_propagating_to_sales_book(): void
    {
        Storage::fake('public');
        config(['services.gemini.api_key' => 'test-key-123']);

        $report = FiscalZReport::create([
            'report_number' => 905,
            'report_date' => '2026-05-10',
            'opening_time' => '08:00:00',
            'closing_time' => '20:00:00',
            'first_invoice_number' => '000010',
            'last_invoice_number' => '000011',
            'invoices_count' => 2,
            'exempt_amount' => 50.00,
            'base_16_amount' => 100.00,
            'iva_amount' => 16.00,
            'igtf_base_amount' => 0.00,
            'igtf_amount' => 0.00,
            'total_amount' => 166.00,
            'status' => 'closed',
        ]);

        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response([
                'candidates' => [[
                    'content' => [
                        'parts' => [[
                            'text' => json_encode([
                                'match' => true,
                                'notes' => 'Montos coinciden exactamente.',
                                'extracted_data' => [
                                    'exempt_amount' => 50.00,
                                    'base_16_amount' => 100.00,
                                    'iva_amount' => 16.00,
                                    'igtf_amount' => 0.00,
                                    'total_amount' => 166.00,
                                ],
                                'discrepancies' => [],
                            ]),
                        ]],
                    ],
                ]],
            ], 200),
        ]);

        $image = UploadedFile::fake()->image('zreport905.jpg');
        $verified = $this->service->verifyImageWithAi($report->id, $image);

        $this->assertEquals('COMPROBADO', $verified->status);
        $this->assertNotNull($verified->image_path);

        $zipPath = $this->service->createImagesZip([]);
        $this->assertNotNull($zipPath);
        $this->assertFileExists($zipPath);
        @unlink($zipPath);

        // Crear facturas y libro de ventas abierto para probar updateRangeAndRecalculate
        FiscalHistory::create([
            'user_id' => $this->user->id,
            'business_name' => 'A',
            'identification' => 'V-1',
            'invoice_date' => '2026-05-10',
            'invoice_number' => '000020',
            'exempt_amount' => 30.00,
            'taxable_amount' => 100.00,
            'iva_amount' => 16.00,
            'spe_surcharge_amount' => 3.00,
            'total_amount' => 149.00,
        ]);

        $salesBook = SalesBook::create([
            'period_type' => 'monthly',
            'month' => 5,
            'year' => 2026,
            'start_date' => '2026-05-01',
            'end_date' => '2026-05-31',
            'status' => 'open',
        ]);

        $entry = SalesBookEntry::create([
            'sales_book_id' => $salesBook->id,
            'fiscal_z_report_id' => $report->id,
            'operation_number' => 1,
            'operation_date' => '2026-05-10',
            'document_type' => 'REPORTE Z',
            'z_report_number' => '905',
            'non_taxable_sales' => 50.00,
            'taxpayer_taxable_base' => 100.00,
            'taxpayer_aliquot' => 0.16,
            'taxpayer_vat' => 16.00,
            'total_sales_with_vat' => 166.00,
            'igtf_amount' => 0.00,
        ]);

        $updated = $this->service->updateRangeAndRecalculate($report->id, [
            'first_invoice_number' => '000020',
            'last_invoice_number' => '000025',
            'recalculate' => true,
        ]);

        $this->assertEquals(149.00, (float) $updated->total_amount);
        $entry->refresh();
        $this->assertEquals(30.00, (float) $entry->non_taxable_sales);
        $this->assertEquals(146.00, (float) $entry->total_sales_with_vat); // excluye IGTF 3.00

        // Modo manual (recalculate = false)
        $manual = $this->service->updateRangeAndRecalculate($report->id, [
            'first_invoice_number' => '000020',
            'last_invoice_number' => '000030',
            'recalculate' => false,
            'exempt_amount' => 40.00,
            'base_16_amount' => 200.00,
            'iva_amount' => 32.00,
            'igtf_amount' => 5.00,
        ]);

        $this->assertEquals(277.00, (float) $manual->total_amount);
    }
}
