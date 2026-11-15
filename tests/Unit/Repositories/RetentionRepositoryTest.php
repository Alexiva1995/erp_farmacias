<?php

declare(strict_types=1);

namespace Tests\Unit\Repositories;

use App\Enums\RetentionType;
use App\Models\GeneralSetting;
use App\Models\Invoice;
use App\Models\Retention;
use App\Models\Role;
use App\Models\Supplier;
use App\Models\User;
use App\Repositories\RetentionRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RetentionRepositoryTest extends TestCase
{
    use RefreshDatabase;

    private RetentionRepository $repository;
    private User $admin;
    private Supplier $supplier;

    protected function setUp(): void
    {
        parent::setUp();

        $this->repository = new RetentionRepository();

        $role = Role::firstOrCreate(
            ['id' => 1],
            ['name' => 'Admin', 'guard_name' => 'web', 'menu_permissions' => ['*']]
        );

        $this->admin = User::factory()->create(['role_id' => $role->id, 'is_active' => true]);
        $this->supplier = Supplier::create([
            'name' => 'Drogueria Caracas',
            'social_reason' => 'Drogueria Caracas C.A.',
            'rif' => 'J-30112233-4',
            'address' => 'Av. Libertador, Caracas',
        ]);

        GeneralSetting::create([
            'app_name' => 'Farmacia Central C.A.',
            'app_rif' => 'J-40998877-6',
            'address' => 'Calle Principal #10',
        ]);
    }

    public function test_lists_and_generates_chunked_iva_retentions_prepares_updates_omits_and_deletes(): void
    {
        $invoiceIds = [];
        for ($i = 1; $i <= 9; $i++) {
            $invoice = Invoice::create([
                'supplier_id' => $this->supplier->id,
                'uploaded_by' => $this->admin->id,
                'registered_by' => $this->admin->id,
                'loaded_by' => $this->admin->id,
                'ordered_by' => $this->admin->id,
                'invoice_number' => 'FAC-00' . $i,
                'control_number' => '00-000' . $i,
                'currency' => 'Bs',
                'is_fiscal' => true,
                'retention_generated' => false,
                'created_invoice_date' => '2026-04-0' . $i,
                'received_date' => '2026-04-0' . $i,
                'payment_date' => '2026-04-0' . $i,
                'exp_date' => '2026-05-0' . $i,
                'exempt_amount' => 10.00,
                'taxable_base' => 100.00,
                'tax_amount' => 16.00,
                'total_amount' => 126.00,
                'total_usd' => 3.00,
                'status' => 'loaded',
                'status_payment' => 0,
            ]);
            $invoiceIds[] = $invoice->id;
        }

        // Facturas pendientes de retención IVA
        $pending = $this->repository->getInvoicesWithTax([
            'retention_type' => 'iva',
            'is_generated' => false,
            'search' => 'FAC-00',
        ], 15);
        $this->assertSame(9, $pending->total());

        // Generación particionada (máximo 7 facturas por comprobante)
        $retentions = $this->repository->generateRetentions($invoiceIds, '2026-04-15');
        $this->assertCount(2, $retentions);
        $this->assertSame(7, $retentions[0]->invoices()->count());
        $this->assertSame(2, $retentions[1]->invoices()->count());

        // Listado de retenciones generadas
        $generated = $this->repository->getGeneratedRetentions([
            'retention_type' => 'iva',
            'search' => 'Drogueria',
        ], 15);
        $this->assertSame(2, $generated->total());

        $firstRetention = $retentions[0];

        // Preparación de datos de impresión
        $prepared = $this->repository->prepareRetentionData($firstRetention);
        $this->assertArrayHasKey('comprobante', $prepared);
        $this->assertArrayHasKey('supplier', $prepared);
        $this->assertArrayHasKey('totals', $prepared);

        // Actualización de número de comprobante y actualización de facturas asociadas
        $updatedRet = $this->repository->updateRetention($firstRetention->id, [
            'number' => '20260400007777',
        ]);
        $this->assertSame('20260400007777', $updatedRet->number);

        $withInvoices = $this->repository->getRetentionWithInvoices($firstRetention->id);
        $this->assertSame($firstRetention->id, $withInvoices->id);

        $subsetData = array_map(
            fn (int $index, int $id) => [
                'id' => $id,
                'invoice_number' => 'FAC-00' . ($index + 1),
                'control_number' => '00-000' . ($index + 1),
                'taxable_base' => 100.00,
                'tax_amount' => 16.00,
                'exempt_amount' => 10.00,
                'total_amount' => 126.00,
            ],
            array_keys(array_slice($invoiceIds, 0, 3)),
            array_slice($invoiceIds, 0, 3)
        );
        $modifiedRet = $this->repository->updateRetentionWithInvoices(
            $firstRetention->id,
            [
                'number' => '20260400008888',
                'date' => '2026-04-16',
            ],
            $subsetData
        );
        $this->assertSame('20260400008888', $modifiedRet->number);
        $this->assertSame(3, $modifiedRet->invoices()->count());

        // Eliminación revierte las facturas a estado pendiente
        $this->assertTrue($this->repository->deleteRetention($firstRetention->id));
        $this->assertFalse((bool) Invoice::findOrFail($invoiceIds[0])->retention_generated);

        // Generación masiva por rango de fechas para las pendientes
        $batchCount = $this->repository->generateAllPendingInRange('2026-04-01', '2026-04-30', '2026-04-20');
        $this->assertGreaterThanOrEqual(1, $batchCount);

        // Omitir y restaurar facturas para retención
        $extraInvoice = Invoice::create([
            'supplier_id' => $this->supplier->id,
            'uploaded_by' => $this->admin->id,
            'registered_by' => $this->admin->id,
            'loaded_by' => $this->admin->id,
            'ordered_by' => $this->admin->id,
            'invoice_number' => 'FAC-OMIT-1',
            'control_number' => '00-9991',
            'currency' => 'Bs',
            'is_fiscal' => true,
            'retention_generated' => false,
            'created_invoice_date' => '2026-04-10',
            'received_date' => '2026-04-10',
            'payment_date' => '2026-04-10',
            'exp_date' => '2026-05-10',
            'taxable_base' => 50.00,
            'tax_amount' => 8.00,
            'total_amount' => 58.00,
            'status' => 'loaded',
            'status_payment' => 0,
        ]);

        $omitted = $this->repository->omitInvoicesUntilDate('2026-04-20');
        $this->assertGreaterThanOrEqual(1, $omitted);
        $this->assertTrue((bool) $extraInvoice->fresh()->retention_generated);

        $restored = $this->repository->restoreOmittedInvoices();
        $this->assertGreaterThanOrEqual(1, $restored);
        $this->assertFalse((bool) $extraInvoice->fresh()->retention_generated);
    }

    public function test_validates_supplier_rif_and_manages_islr_retention_correlative(): void
    {
        $incompleteSupplier = Supplier::create([
            'name' => 'Proveedor Sin Direccion',
            'rif' => '',
            'address' => '',
        ]);

        $badInvoice = Invoice::create([
            'supplier_id' => $incompleteSupplier->id,
            'uploaded_by' => $this->admin->id,
            'registered_by' => $this->admin->id,
            'invoice_number' => 'BAD-1',
            'control_number' => 'CTRL-BAD-1',
            'currency' => 'Bs',
            'is_fiscal' => true,
            'created_invoice_date' => '2026-04-12',
            'received_date' => '2026-04-12',
            'payment_date' => '2026-04-12',
            'exp_date' => '2026-05-12',
            'taxable_base' => 100.00,
            'tax_amount' => 16.00,
            'total_amount' => 116.00,
            'status' => 'loaded',
            'status_payment' => 0,
        ]);

        try {
            $this->repository->generateRetentions([$badInvoice->id]);
            $this->fail('Se esperaba excepción cuando el proveedor no tiene RIF ni dirección.');
        } catch (\Exception $e) {
            $this->assertStringContainsString('RIF', $e->getMessage());
        }

        // Flujo ISLR
        $islrInvoice = Invoice::create([
            'supplier_id' => $this->supplier->id,
            'uploaded_by' => $this->admin->id,
            'registered_by' => $this->admin->id,
            'loaded_by' => $this->admin->id,
            'ordered_by' => $this->admin->id,
            'invoice_number' => 'ISLR-001',
            'control_number' => 'CTRL-ISLR-1',
            'currency' => 'Bs',
            'is_fiscal' => true,
            'retention_generated' => false,
            'has_islr_retention' => true,
            'islr_retention_generated' => false,
            'created_invoice_date' => '2026-04-14',
            'received_date' => '2026-04-14',
            'payment_date' => '2026-04-14',
            'exp_date' => '2026-05-14',
            'taxable_base' => 1000.00,
            'tax_amount' => 160.00,
            'total_amount' => 1160.00,
            'status' => 'loaded',
            'status_payment' => 0,
        ]);

        $pendingIslr = $this->repository->getInvoicesWithTax([
            'retention_type' => 'islr',
            'is_generated' => false,
        ], 15);
        $this->assertSame(1, $pendingIslr->total());

        $correlative = $this->repository->getNextIslrCorrelative();
        $this->assertArrayHasKey('next_number', $correlative);

        $islrRetention = $this->repository->generateIslrRetention([
            'invoice_ids' => [$islrInvoice->id],
            'concept_description' => 'Servicio de flete nacional',
            'retention_percentage' => 3.0,
            'subtrahend' => 1.50,
            'date' => '2026-04-18',
            'starting_number' => '20260400000001',
        ]);

        $this->assertSame(RetentionType::ISLR, $islrRetention->type);
        $this->assertEquals(28.50, (float) $islrRetention->total_withheld_amount);
        $this->assertTrue((bool) $islrInvoice->fresh()->islr_retention_generated);

        $preparedIslr = $this->repository->prepareIslrRetentionData($islrRetention);
        $this->assertArrayHasKey('comprobante_number', $preparedIslr);
        $this->assertArrayHasKey('operations', $preparedIslr);
        $this->assertArrayHasKey('totals', $preparedIslr);
    }
}
