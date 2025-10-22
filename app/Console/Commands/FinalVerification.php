<?php

namespace App\Console\Commands;

use App\Models\Invoice;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class FinalVerification extends Command
{
    protected $signature = 'app:final-verification';
    protected $description = 'Verificación final del Issue #8';

    public function handle()
    {
        $this->info('=== VERIFICACIÓN FINAL DEL ISSUE #8 ===');

        // 1. Verificar tipo de columna
        $this->info('1. Verificando tipo de columna:');
        $columnInfo = DB::select("DESCRIBE invoices status_payment");
        $this->info("   Tipo de columna: {$columnInfo[0]->Type}");

        // 2. Verificar valores únicos
        $this->newLine();
        $this->info('2. Verificando valores únicos:');
        $result = DB::select('SELECT DISTINCT status_payment FROM invoices');
        foreach ($result as $row) {
            $type = gettype($row->status_payment);
            $this->info("   Valor: '{$row->status_payment}', Tipo: {$type}");
        }

        // 3. Verificar que no hay valores string antiguos
        $this->newLine();
        $this->info('3. Verificando valores string antiguos:');
        $oldValues = DB::select("SELECT COUNT(*) as count FROM invoices WHERE status_payment IN ('paid', 'unpaid', 'pending', 'partial')");
        $this->info("   Valores string antiguos encontrados: " . $oldValues[0]->count);

        // 4. Verificar facturas pendientes
        $this->newLine();
        $this->info('4. Verificando facturas pendientes:');
        $pendingCount = DB::select("SELECT COUNT(*) as count FROM invoices WHERE status_payment = 0 OR status_payment IS NULL");
        $this->info("   Total facturas pendientes: " . $pendingCount[0]->count);

        // 5. Verificar facturas pagadas
        $this->info('5. Verificando facturas pagadas:');
        $paidCount = DB::select("SELECT COUNT(*) as count FROM invoices WHERE status_payment = 1");
        $this->info("   Total facturas pagadas: " . $paidCount[0]->count);

        // 6. Mostrar ejemplos
        $this->newLine();
        $this->info('6. Ejemplos de facturas:');
        $examples = DB::select("SELECT id, invoice_number, status_payment, status FROM invoices LIMIT 3");
        foreach ($examples as $invoice) {
            $statusText = $invoice->status_payment == 1 ? 'Pagada' : 'Pendiente';
            $this->info("   ID: {$invoice->id}, Número: {$invoice->invoice_number}, Status Payment: {$invoice->status_payment} ({$statusText}), Status: {$invoice->status}");
        }

        $this->newLine();
        if ($oldValues[0]->count == 0) {
            $this->info('✅ VERIFICACIÓN EXITOSA: El Issue #8 está funcionando correctamente!');
        } else {
            $this->error('❌ VERIFICACIÓN FALLIDA: Aún hay valores string antiguos');
        }
    }
}
