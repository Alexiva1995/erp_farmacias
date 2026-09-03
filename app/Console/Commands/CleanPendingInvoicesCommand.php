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
                            {--deduplicate : Elimina los registros duplicados más recientes dejando el original}
                            {--clean-already-processed : Elimina facturas pendientes si ya existe una versión cargada, por ordenar u ordenada}
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
        $deduplicate = $this->option('deduplicate');
        $cleanProcessed = $this->option('clean-already-processed');
        $fixDates = $this->option('fix-dates');
        $closeSettled = $this->option('close-settled-dronena');
        $deleteZero = $this->option('delete-zero-amounts');

        $this->info($isDryRun ? '=== MODO SIMULACIÓN (DRY-RUN: NO SE MODIFICARÁ LA BD) ===' : '=== EJECUTANDO DEPURACIÓN Y LIMPIEZA ===');

        // -1. Limpiar pendientes si ya existe otra factura idéntica ya cargada (loaded), por ordenar (to_order) u ordenada (ordered)
        if ($cleanProcessed || $deduplicate) {
            $this->info("\n--- Buscando facturas pendientes que ya fueron procesadas en Cargas/Ordenadas ---");
            $pendingInvoices = Invoice::where('status', 'pending')->get();
            $removedProcessed = 0;

            foreach ($pendingInvoices as $pending) {
                $cleanNum = ltrim((string)$pending->invoice_number, 'A');
                
                $existsProcessed = Invoice::where('supplier_id', $pending->supplier_id)
                    ->where('id', '!=', $pending->id)
                    ->whereIn('status', ['loaded', 'to_order', 'ordered'])
                    ->where(function($q) use ($pending, $cleanNum) {
                        $q->where('invoice_number', $pending->invoice_number)
                          ->orWhere('invoice_number', 'LIKE', "%{$cleanNum}%");
                    })
                    ->first();

                if ($existsProcessed) {
                    $this->warn("  -> Factura Pendiente ID {$pending->id} (#{$pending->invoice_number}) ya existe procesada con ID {$existsProcessed->id} (Estado: {$existsProcessed->status}). Eliminando pendiente redundante...");
                    if (!$isDryRun) {
                        $pending->details()->delete();
                        $pending->delete();
                    }
                    $removedProcessed++;
                }
            }

            $this->info("✅ Se " . ($isDryRun ? 'detectaron' : 'eliminaron') . " {$removedProcessed} facturas pendientes que ya estaban cargadas.");
        }

        // 0. Eliminación de facturas duplicadas (conservando la original más antigua o con datos completos)
        if ($deduplicate) {
            $this->info("\n--- 0. Buscando y eliminando facturas duplicadas más recientes ---");
            $allInvoices = Invoice::withCount('details')->orderBy('id', 'asc')->get();
            $grouped = [];
            foreach ($allInvoices as $inv) {
                $cleanNum = ltrim((string)$inv->invoice_number, 'A');
                $key = "{$inv->supplier_id}_{$cleanNum}";
                $grouped[$key][] = $inv;
            }

            $deletedCount = 0;
            foreach ($grouped as $key => $list) {
                if (count($list) <= 1) continue;

                // Ordenar: primero los que tienen productos (details_count desc), luego por id asc (más antiguos)
                usort($list, function ($a, $b) {
                    if ($a->details_count !== $b->details_count) {
                        return $b->details_count <=> $a->details_count;
                    }
                    return $a->id <=> $b->id;
                });

                $keeper = $list[0];
                $this->line("Manteniendo Factura Principal: ID {$keeper->id} | {$keeper->invoice_number} | Control: " . ($keeper->control_number ?? 'NULL') . " | Items: {$keeper->details_count}");

                for ($i = 1; $i < count($list); $i++) {
                    $dup = $list[$i];
                    $this->warn("  -> Eliminando Duplicado Reciente: ID {$dup->id} | {$dup->invoice_number} | Control: " . ($dup->control_number ?? 'NULL') . " | Creada: {$dup->created_at}");
                    if (!$isDryRun) {
                        // Eliminar detalles huérfanos si los tuviera y borrar factura
                        $dup->details()->delete();
                        $dup->delete();
                    }
                    $deletedCount++;
                }
            }

            $this->info("✅ Se " . ($isDryRun ? 'detectaron para eliminar' : 'eliminaron') . " {$deletedCount} facturas duplicadas recientes.");
        }

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
