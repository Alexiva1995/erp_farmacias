<?php

declare(strict_types=1);

namespace Tests\Unit\Repositories;

use App\Enums\PurchaseBookStatus;
use App\Models\FiscalCommand;
use App\Models\FiscalZReport;
use App\Models\GeneralSetting;
use App\Models\Invoice;
use App\Models\Retention;
use App\Models\Role;
use App\Models\Supplier;
use App\Models\User;
use App\Repositories\PurchaseBookRepository;
use App\Repositories\SalesBookRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PurchaseAndSalesBookRepositoryTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private Supplier $supplier;

    protected function setUp(): void
    {
        parent::setUp();

        $role = Role::firstOrCreate(
            ['id' => 1],
            ['name' => 'Admin', 'guard_name' => 'web', 'menu_permissions' => ['*']]
        );
        $this->admin = User::factory()->create(['role_id' => $role->id, 'is_active' => true]);
        $this->supplier = Supplier::create([
            'name' => 'Drogueria Andina',
            'social_reason' => 'Drogueria Andina C.A.',
            'rif' => 'J-30555666-7',
            'address' => 'Zona Industrial, Valencia',
        ]);

        GeneralSetting::create([
            'app_name' => 'Farmacia Test C.A.',
            'app_rif' => 'J-12345678-9',
            'enable_zoom' => false,
        ]);
    }

    public function test_purchase_book_syncs_invoices_retentions_manages_manual_entries_and_closes(): void
    {
        $repo = new PurchaseBookRepository();

        // Factura alícuota general (16%) y retención dentro de la 1ra quincena de abril 2026
        $invoiceGeneral = Invoice::create([
            'supplier_id' => $this->supplier->id,
            'uploaded_by' => $this->admin->id,
            'registered_by' => $this->admin->id,
            'invoice_number' => 'COMP-001',
            'control_number' => '00-001001',
            'currency' => 'Bs',
            'is_fiscal' => true,
            'created_invoice_date' => '2026-04-05',
            'received_date' => '2026-04-05',
            'payment_date' => '2026-04-05',
            'exp_date' => '2026-05-05',
            'exempt_amount' => 20.00,
            'taxable_base' => 100.00,
            'tax_amount' => 16.00,
            'total_amount' => 136.00,
            'status' => 'loaded',
            'status_payment' => 0,
        ]);

        $retention = Retention::create([
            'supplier_id' => $this->supplier->id,
            'type' => 'iva',
            'number' => '20260400000001',
            'date' => '2026-04-06',
            'total_taxable_base' => 100.00,
            'total_tax_amount' => 16.00,
            'total_withheld_amount' => 12.00,
        ]);
        $invoiceGeneral->update([
            'retention_id' => $retention->id,
            'retention_generated' => true,
        ]);

        // Factura alícuota reducida (8%)
        Invoice::create([
            'supplier_id' => $this->supplier->id,
            'uploaded_by' => $this->admin->id,
            'registered_by' => $this->admin->id,
            'invoice_number' => 'COMP-002',
            'control_number' => '00-001002',
            'currency' => 'Bs',
            'is_fiscal' => true,
            'created_invoice_date' => '2026-04-08',
            'received_date' => '2026-04-08',
            'payment_date' => '2026-04-08',
            'exp_date' => '2026-05-08',
            'exempt_amount' => 0.00,
            'taxable_base' => 100.00,
            'tax_amount' => 8.00,
            'total_amount' => 108.00,
            'status' => 'loaded',
            'status_payment' => 0,
        ]);

        $book = $repo->findOrCreateBook(2026, 4, 1);
        $this->assertSame(PurchaseBookStatus::OPEN, $book->status);

        $syncedBook = $repo->syncTransactions($book);
        $this->assertGreaterThanOrEqual(3, $syncedBook->entries->count()); // 2 facturas + 1 retención
        $this->assertEquals(12.00, (float) $syncedBook->total_vat_withheld);
        $this->assertEquals(8.00, (float) $syncedBook->total_reduced_vat);

        // Registro manual, edición y eliminación (excluyendo una factura del sistema)
        $manualEntry = $repo->storeEntry($syncedBook, [
            'operation_date' => '2026-04-10',
            'rif' => 'J-20000000-1',
            'supplier_name' => 'Proveedor Manual S.R.L.',
            'invoice_number' => 'MAN-99',
            'control_number' => 'CTRL-99',
            'document_type' => 'FACTURA',
            'transaction_type' => '01-REG',
            'total_purchases_with_vat' => 116.00,
            'exempt_purchases' => 0.00,
            'general_taxable_base' => 100.00,
            'general_aliquot' => 16.00,
            'general_vat' => 16.00,
        ]);

        $updatedEntry = $repo->updateEntry($syncedBook, $manualEntry->id, [
            'operation_date' => '2026-04-10',
            'rif' => 'J-20000000-1',
            'supplier_name' => 'Proveedor Manual Editado',
            'invoice_number' => 'MAN-99',
            'document_type' => 'FACTURA',
            'transaction_type' => '01-REG',
            'total_purchases_with_vat' => 232.00,
            'exempt_purchases' => 0.00,
            'general_taxable_base' => 200.00,
            'general_aliquot' => 16.00,
            'general_vat' => 32.00,
        ]);
        $this->assertSame('Proveedor Manual Editado', $updatedEntry->supplier_name);
        $this->assertTrue((bool) $updatedEntry->is_manual_override);

        $this->assertTrue($repo->deleteEntry($syncedBook, $manualEntry->id, false));

        // Búsqueda de facturas y listado mientras está abierto
        $foundInvoices = $repo->searchInvoices('COMP-001');
        $this->assertNotEmpty($foundInvoices);

        $openList = $repo->getBooks(['year' => 2026, 'month' => 4], 15);
        $this->assertSame(1, $openList->total());

        $loadedOpen = $repo->getBookById($book->id);
        $this->assertSame($book->id, $loadedOpen->id);

        // Cierre del libro
        $closedBook = $repo->closeBook($book, $this->admin->id);
        $this->assertSame(PurchaseBookStatus::CLOSED, $closedBook->status);
    }

    public function test_sales_book_syncs_z_reports_and_normalizes_credit_note_entries(): void
    {
        $repo = new SalesBookRepository();

        FiscalZReport::create([
            'report_number' => 15,
            'report_date' => '2026-04-04',
            'opening_time' => '08:00:00',
            'closing_time' => '20:00:00',
            'status' => 'closed',
            'first_invoice_number' => '00001001',
            'last_invoice_number' => '00001010',
            'invoices_count' => 10,
            'exempt_amount' => 50.00,
            'base_16_amount' => 200.00,
            'iva_amount' => 32.00,
            'total_amount' => 282.00,
            'igtf_base_amount' => 0.00,
            'igtf_amount' => 5.00,
        ]);

        FiscalCommand::create([
            'command' => 'CREDIT_NOTE',
            'status' => 'success',
            'payload' => [
                'invoice_number' => '00001002',
                'machine_serial' => 'Z1B999888',
                'invoice_date' => '2026-04-04',
                'invoice_hour' => '10:30',
                'refund_amount' => 58.00,
                'client_name' => 'Cliente Devolución',
                'identification' => 'V-14111222',
            ],
            'response' => json_encode(['credit_note_number' => 'NC-0001']),
            'created_at' => '2026-04-04 11:00:00',
        ]);

        $book = $repo->findOrCreateBook(2026, 4, 1);
        $syncedBook = $repo->syncTransactions($book);

        $this->assertGreaterThanOrEqual(1, $syncedBook->entries->count());
        $this->assertNotEmpty($repo->searchZReports('15'));

        // Alta de nota de crédito manual: el repositorio debe normalizar los montos a negativo
        $creditEntry = $repo->storeEntry($syncedBook, [
            'operation_date' => '2026-04-07',
            'identification' => 'V-14111222',
            'client_name' => 'Cliente Nota Credito',
            'document_type' => 'NOTA DE CRÉDITO',
            'credit_note_number' => 'NC-MANUAL-1',
            'affected_invoice_number' => '00001002',
            'transaction_type' => '03-REG',
            'total_sales_with_vat' => 116.00,
            'non_taxable_sales' => 0.00,
            'non_taxpayer_taxable_base' => 100.00,
            'non_taxpayer_aliquot' => 16.00,
            'non_taxpayer_vat' => 16.00,
        ]);

        $this->assertLessThan(0, (float) $creditEntry->total_sales_with_vat);
        $this->assertLessThan(0, (float) $creditEntry->non_taxpayer_vat);

        // Actualización de entrada y eliminación
        $updatedEntry = $repo->updateEntry($syncedBook, $creditEntry->id, [
            'operation_date' => '2026-04-07',
            'identification' => 'V-14111222',
            'client_name' => 'Cliente Nota Editada',
            'document_type' => 'NOTA DE CRÉDITO',
            'credit_note_number' => 'NC-MANUAL-1',
            'affected_invoice_number' => '00001002',
            'transaction_type' => '03-REG',
            'total_sales_with_vat' => 58.00,
            'non_taxable_sales' => 0.00,
            'non_taxpayer_taxable_base' => 50.00,
            'non_taxpayer_aliquot' => 16.00,
            'non_taxpayer_vat' => 8.00,
        ]);
        $this->assertEquals(-58.00, (float) $updatedEntry->total_sales_with_vat);

        $this->assertTrue($repo->deleteEntry($syncedBook, $creditEntry->id));

        $openList = $repo->getBooks(['year' => 2026, 'month' => 4], 15);
        $this->assertSame(1, $openList->total());

        $closedBook = $repo->closeBook($book, $this->admin->id);
        $this->assertSame(PurchaseBookStatus::CLOSED, $closedBook->status);
    }
}
