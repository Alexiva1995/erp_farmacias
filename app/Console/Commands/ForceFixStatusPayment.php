<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ForceFixStatusPayment extends Command
{
    protected $signature = 'app:force-fix-status-payment';
    protected $description = 'Forzar corrección de valores status_payment';

    public function handle()
    {
        $this->info('=== FORZANDO CORRECCIÓN DE VALORES status_payment ===');

        // Verificar valores actuales
        $this->info('1. Verificando valores actuales:');
        $oldValues = DB::select("SELECT COUNT(*) as count FROM invoices WHERE status_payment IN ('paid', 'unpaid', 'pending', 'partial')");
        $this->info("   Valores string antiguos: " . $oldValues[0]->count);

        if ($oldValues[0]->count > 0) {
            $this->newLine();
            $this->info('2. Corrigiendo valores string antiguos...');

            // Primero, cambiar temporalmente el tipo de columna a string
            $this->info('   Cambiando tipo de columna a string temporalmente...');
            DB::statement("ALTER TABLE invoices MODIFY COLUMN status_payment VARCHAR(20)");

            // Corregir valores
            $this->info('   Corrigiendo valores...');
            $paidCount = DB::update("UPDATE invoices SET status_payment = '1' WHERE status_payment = 'paid'");
            $this->info("   Valores 'paid' corregidos: {$paidCount}");

            $unpaidCount = DB::update("UPDATE invoices SET status_payment = '0' WHERE status_payment IN ('unpaid', 'pending', 'partial')");
            $this->info("   Valores 'unpaid', 'pending', 'partial' corregidos: {$unpaidCount}");

            // Cambiar el tipo de columna de vuelta a integer
            $this->info('   Cambiando tipo de columna a integer...');
            DB::statement("ALTER TABLE invoices MODIFY COLUMN status_payment INT(11) DEFAULT 0");

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
