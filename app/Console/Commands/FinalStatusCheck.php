<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class FinalStatusCheck extends Command
{
    protected $signature = 'app:final-status-check';
    protected $description = 'Verificación final del status_payment';

    public function handle()
    {
        $this->info('=== VERIFICACIÓN FINAL DEL status_payment ===');

        // Verificar tipo de columna
        $this->info('1. Verificando tipo de columna:');
        $columnInfo = DB::select("DESCRIBE invoices status_payment");
        $this->info("   Tipo: {$columnInfo[0]->Type}");

        // Verificar valores únicos
        $this->newLine();
        $this->info('2. Verificando valores únicos:');
        $result = DB::select('SELECT DISTINCT status_payment FROM invoices');
        foreach ($result as $row) {
            $type = gettype($row->status_payment);
            $this->info("   Valor: '{$row->status_payment}', Tipo: {$type}");
        }

        // Verificar valores específicos
        $this->newLine();
        $this->info('3. Verificando valores específicos:');

        $paidCount = DB::select("SELECT COUNT(*) as count FROM invoices WHERE status_payment = 'paid'");
        $this->info("   Valores 'paid': " . $paidCount[0]->count);

        $unpaidCount = DB::select("SELECT COUNT(*) as count FROM invoices WHERE status_payment = 'unpaid'");
        $this->info("   Valores 'unpaid': " . $unpaidCount[0]->count);

        $pendingCount = DB::select("SELECT COUNT(*) as count FROM invoices WHERE status_payment = 'pending'");
        $this->info("   Valores 'pending': " . $pendingCount[0]->count);

        $partialCount = DB::select("SELECT COUNT(*) as count FROM invoices WHERE status_payment = 'partial'");
        $this->info("   Valores 'partial': " . $partialCount[0]->count);

        // Verificar valores numéricos
        $this->newLine();
        $this->info('4. Verificando valores numéricos:');
        $zeroCount = DB::select("SELECT COUNT(*) as count FROM invoices WHERE status_payment = 0");
        $this->info("   Valores 0: " . $zeroCount[0]->count);

        $oneCount = DB::select("SELECT COUNT(*) as count FROM invoices WHERE status_payment = 1");
        $this->info("   Valores 1: " . $oneCount[0]->count);

        // Verificar total
        $this->newLine();
        $this->info('5. Verificando total:');
        $totalCount = DB::select("SELECT COUNT(*) as count FROM invoices");
        $this->info("   Total facturas: " . $totalCount[0]->count);

        // Verificar si hay valores string antiguos reales
        $this->newLine();
        $this->info('6. Verificando valores string antiguos reales:');
        $realStringCount = DB::select("SELECT COUNT(*) as count FROM invoices WHERE status_payment IN ('paid', 'unpaid', 'pending', 'partial')");
        $this->info("   Valores string antiguos reales: " . $realStringCount[0]->count);

        if ($realStringCount[0]->count == 0) {
            $this->info('✅ VERIFICACIÓN EXITOSA: No hay valores string antiguos reales');
        } else {
            $this->error('❌ VERIFICACIÓN FALLIDA: Aún hay valores string antiguos reales');
        }
    }
}
