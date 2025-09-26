<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class FixStatusPaymentValues extends Command
{
    protected $signature = 'app:fix-status-payment-values';
    protected $description = 'Corregir valores del campo status_payment';

    public function handle()
    {
        $this->info('=== CORRIGIENDO VALORES DEL CAMPO status_payment ===');

        // Verificar valores actuales
        $this->info('1. Verificando valores actuales:');
        $oldValues = DB::select("SELECT COUNT(*) as count FROM invoices WHERE status_payment IN ('paid', 'unpaid', 'pending', 'partial')");
        $this->info("   Valores string antiguos: " . $oldValues[0]->count);

        $numericValues = DB::select("SELECT COUNT(*) as count FROM invoices WHERE status_payment IN (0, 1)");
        $this->info("   Valores numéricos: " . $numericValues[0]->count);

        if ($oldValues[0]->count > 0) {
            $this->newLine();
            $this->info('2. Corrigiendo valores string antiguos...');

            // Corregir valores 'paid' a 1
            $paidCount = DB::update("UPDATE invoices SET status_payment = 1 WHERE status_payment = 'paid'");
            $this->info("   Valores 'paid' corregidos: {$paidCount}");

            // Corregir valores 'unpaid', 'pending', 'partial' a 0
            $unpaidCount = DB::update("UPDATE invoices SET status_payment = 0 WHERE status_payment IN ('unpaid', 'pending', 'partial')");
            $this->info("   Valores 'unpaid', 'pending', 'partial' corregidos: {$unpaidCount}");

            $this->newLine();
            $this->info('3. Verificando corrección...');

            $oldValuesAfter = DB::select("SELECT COUNT(*) as count FROM invoices WHERE status_payment IN ('paid', 'unpaid', 'pending', 'partial')");
            $this->info("   Valores string antiguos restantes: " . $oldValuesAfter[0]->count);

            $numericValuesAfter = DB::select("SELECT COUNT(*) as count FROM invoices WHERE status_payment IN (0, 1)");
            $this->info("   Valores numéricos: " . $numericValuesAfter[0]->count);

            if ($oldValuesAfter[0]->count == 0) {
                $this->info('✅ Corrección exitosa!');
            } else {
                $this->error('❌ Aún hay valores string antiguos');
            }
        } else {
            $this->info('✅ No hay valores string antiguos que corregir');
        }
    }
}
