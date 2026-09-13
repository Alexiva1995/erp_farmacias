<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Chronic\ChronicClientService;
use Illuminate\Console\Command;
use Illuminate\Http\Request;

class ChronicClientsAuditCommand extends Command
{
    protected $signature = 'crm:chronic-audit
                            {--classify-ai : Ejecutar la sincronización y clasificación automática por IA / heurística}
                            {--set-product= : ID del producto a clasificar manualmente}
                            {--type=chronic : Tipo de consumo (chronic, single_treatment, no_alert, sporadic)}
                            {--days=30 : Días de duración estimada del tratamiento}';

    protected $description = 'Audita el estado de productos y pacientes crónicos en CRM y permite clasificarlos por consola.';

    public function handle(ChronicClientService $service): int
    {
        $this->info("===============================================================");
        $this->info("          AUDITORÍA Y GESTIÓN DE PACIENTES CRÓNICOS (CRM)     ");
        $this->info("===============================================================" . PHP_EOL);

        $productId = $this->option('set-product');
        if ($productId) {
            $type = (string) $this->option('type');
            $days = (int) $this->option('days');

            $this->info("⚙️ Clasificando Producto #{$productId} -> Tipo: {$type}, Días: {$days}...");
            try {
                $updated = $service->updateProductConsumption((int) $productId, [
                    'consumption_type'        => $type,
                    'treatment_duration_days' => $days,
                ]);
                $this->info("✅ Producto [{$updated['id']}] {$updated['name']} configurado exitosamente.");
            } catch (\Throwable $e) {
                $this->error("❌ Error al clasificar producto: " . $e->getMessage());
                return 1;
            }
            $this->newLine();
        }

        if ($this->option('classify-ai')) {
            $this->info("🤖 Ejecutando detección y clasificación clínica automática por IA / Categorías...");
            $aiResult = $service->syncChronicProductsWithAi();
            $this->table(
                ['Total Analizados', 'Crónicos Detectados', 'Actualizados en BD'],
                [[$aiResult['total_analyzed'], $aiResult['chronic_detected'], $aiResult['updated_count']]]
            );
            $this->newLine();
        }

        $this->info("📊 1. ESTADÍSTICAS GLOBALES DEL CRM:");
        $stats = $service->getStats();
        $this->table(
            ['Métrica', 'Cantidad'],
            [
                ['Total Pacientes con Seguimiento', $stats['total_patients']],
                ['Tratamientos Registrados', $stats['total_treatments']],
                ['Recordatorios Urgentes (≤ 5 días)', $stats['urgent_reminders']],
                ['Tratamientos Activos (> 5 días)', $stats['active_treatments']],
                ['Tratamientos Vencidos / Agotados', $stats['expired_treatments']],
                ['Pacientes con Teléfono Válido', $stats['patients_with_phone']],
            ]
        );
        $this->newLine();

        $this->info("📦 2. PRODUCTOS RECIENTES Y SU ESTADO DE CLASIFICACIÓN:");
        $productsConfig = $service->getProductConsumptionConfig(new Request(['itemsPerPage' => 15, 'consumption_type' => 'all']));
        $productRows = collect($productsConfig->items())->map(function ($p) {
            return [
                'ID'        => $p['id'],
                'Producto'  => mb_strimwidth($p['name'], 0, 40, '...'),
                'Tipo'      => $p['consumption_type'] ?: 'SIN CLASIFICAR ⚠️',
                'Días'      => $p['treatment_duration_days'] ?: 'N/A',
                'Precio ($)'=> '$' . number_format((float) $p['sale_price'], 2),
            ];
        })->toArray();

        if (empty($productRows)) {
            $this->warn("No se encontraron productos con ventas recientes para mostrar.");
        } else {
            $this->table(['ID', 'Producto', 'Tipo Consumo', 'Días', 'Precio ($)'], $productRows);
            $this->comment("Total en catálogo: {$productsConfig->total()} productos.");
        }
        $this->newLine();

        $this->info("👥 3. PACIENTES LISTOS PARA CONTACTO POR WHATSAPP:");
        $patients = $service->getChronicPatients(new Request(['itemsPerPage' => 15]));
        $patientRows = collect($patients->items())->map(function ($pt) {
            return [
                'Paciente'     => mb_strimwidth($pt['client_name'], 0, 25, '...'),
                'Teléfono'     => $pt['phone'],
                'Medicamento'  => mb_strimwidth($pt['product_name'], 0, 28, '...'),
                'Últ. Compra'  => $pt['last_order_date_formatted'],
                'Días Rest.'   => $pt['days_until_end'],
                'Fin Estimado' => $pt['treatment_end_date_formatted'],
                'Estado'       => $pt['status_label'],
            ];
        })->toArray();

        if (empty($patientRows)) {
            $this->warn("No hay pacientes para seguimiento. Clasifique productos como \"chronic\", \"single_treatment\" o \"sporadic\" para activarlos.");
        } else {
            $this->table(['Paciente', 'Teléfono', 'Medicamento', 'Últ. Compra', 'Días Rest.', 'Fin Estimado', 'Estado'], $patientRows);
            $this->comment("Total pacientes para seguimiento: {$patients->total()}");
        }

        $this->newLine();
        $this->info("Comandos útiles:");
        $this->line(" • php artisan crm:chronic-audit");
        $this->line(" • php artisan crm:chronic-audit --set-product=14341 --type=chronic --days=30");
        $this->line(" • php artisan crm:chronic-audit --classify-ai");

        return 0;
    }
}
