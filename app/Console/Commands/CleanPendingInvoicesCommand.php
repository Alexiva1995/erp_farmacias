<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Invoice;
use App\Models\Supplier;
use App\Contracts\Suppliers\DronenaScraperServiceInterface;
use Carbon\Carbon;

class CleanPendingInvoicesCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'invoices:clean-pending 
                            {--dry-run : Ejecuta en modo simulación sin alterar la base de datos}
                            {--fix-dates : Asigna fechas por defecto a facturas que tengan fechas NULL}
                            {--close-settled-dronena : Marca como pagadas las facturas de Dronena que ya no existen en el portal activo}
                            {--delete-zero-amounts : Cierra facturas con monto 0 o negativo}';

    /**
     * The command description.
     *
     * @var string
     */
    protected $description = 'Limpia, repara y depura facturas pendientes corruptas o saldadas históricamente';

    public function handle(DronenaScraperServiceInterface $dronenaService): int
    {
        $isDryRun = $this->option('dry-run');
        $fixDates = $this->option('fix-dates');
        $closeSettled = $this->option('close-settled-dronena');
        $deleteZero = $this->option('delete-zero-amounts');

        $this->info($isDryRun ? '=== MODO SIMULACIÓN (DRY-RUN: NO SE MODIFICARÁ LA BD) ===' : '=== EJECUTANDO DEPURACIÓN Y LIMPIEZA ===');

        // 1. Facturas de Dronena saldadas que ya no están en el estado de cuenta activo
        if ($closeSettled || (!$fixDates && !$deleteZero)) {
            $this->info("\n--- 1. Depurando facturas saldadas de Dronena ---");
            $dronenaSupplier = Supplier::where('name', 'LIKE', '%NENA%')->orWhere('name', 'LIKE', '%DRONENA%')->first();
            
            if ($dronenaSupplier) {
                $activeDocs = $dronenaService->fetchDocuments('D719', 'dronena2025');
                $activeNumbers = collect($activeDocs)->pluck('numero_factura')->map(fn($n) => ltrim($n, 'A'))->toArray();

                $dronenaPendingInvoices = Invoice::where('supplier_id', $dronenaSupplier->id)
                    ->where(function($q) {
                        $q->whereNull('status_payment')->orWhere('status_payment', '!=', 1);
                    })->get();

                $toMarkPaid = [];
                foreach ($dronenaPendingInvoices as $inv) {
                    $cleanNum = ltrim($inv->invoice_number, 'A');
                    if (!in_array($cleanNum, $activeNumbers)) {
                        $toMarkPaid[] = $inv;
                    }
                }

                $this->line("Facturas pendientes de Dronena en ERP: {$dronenaPendingInvoices->count()}");
                $this->line("Facturas activas en portal Dronena: " . count($activeNumbers));
                $this->line("Facturas a marcar como pagadas (ya no están en Dronena): " . count($toMarkPaid));

                if (!$isDryRun && count($toMarkPaid) > 0) {
                    $ids = collect($toMarkPaid)->pluck('id');
                    Invoice::whereIn('id', $ids)->update([
                        'status_payment' => 1,
                        'outstanding_debt' => 0,
                    ]);
                    $this->info("✅ Se marcaron como pagadas " . count($toMarkPaid) . " facturas de Dronena.");
                }
            }
        }

        // 2. Facturas con monto en 0 o negativo
        if ($deleteZero || (!$fixDates && !$closeSettled)) {
            $this->info("\n--- 2. Facturas con monto 0 o inválido ---");
            $zeroInvoices = Invoice::where(function($q) {
                $q->whereNull('status_payment')->orWhere('status_payment', '!=', 1);
            })->where(function($q) {
                $q->whereNull('total_amount')
                  ->orWhere('total_amount', '<=', 0)
                  ->orWhereNull('total_usd')
                  ->orWhere('total_usd', '<=', 0);
            })->get();

            $this->line("Facturas pendientes con monto 0 o negativo: {$zeroInvoices->count()}");

            if (!$isDryRun && $zeroInvoices->count() > 0) {
                $ids = $zeroInvoices->pluck('id');
                Invoice::whereIn('id', $ids)->update([
                    'status_payment' => 1,
                    'outstanding_debt' => 0,
                ]);
                $this->info("✅ Se cerraron {$zeroInvoices->count()} facturas con monto 0.");
            }
        }

        // 3. Reparación de fechas vacías
        if ($fixDates || (!$closeSettled && !$deleteZero)) {
            $this->info("\n--- 3. Facturas pendientes sin fecha de vencimiento/pago ---");
            $nullDateInvoices = Invoice::where(function($q) {
                $q->whereNull('status_payment')->orWhere('status_payment', '!=', 1);
            })->where(function($q) {
                $q->whereNull('exp_date')->orWhereNull('payment_date');
            })->get();

            $this->line("Facturas pendientes sin fecha asignada: {$nullDateInvoices->count()}");

            if (!$isDryRun && $nullDateInvoices->count() > 0) {
                $count = 0;
                foreach ($nullDateInvoices as $inv) {
                    $baseDate = $inv->created_invoice_date ?? $inv->received_date ?? $inv->created_at ?? Carbon::now();
                    $dateStr = Carbon::parse($baseDate)->format('Y-m-d');
                    $inv->update([
                        'exp_date' => $inv->exp_date ?: $dateStr,
                        'payment_date' => $inv->payment_date ?: $dateStr,
                    ]);
                    $count++;
                }
                $this->info("✅ Se corrigieron fechas para {$count} facturas.");
            }
        }

        $this->info("\nProceso de auditoría y depuración finalizado.");
        return Command::SUCCESS;
    }
}
