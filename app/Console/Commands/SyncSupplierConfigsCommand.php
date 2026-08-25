<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Supplier;
use App\Models\SupplierConnection;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class SyncSupplierConfigsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'suppliers:sync-configs {--force : Sobrescribir archivos existentes}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sincroniza y crea los archivos de configuración en app/SupplierConfigs según los IDs actuales de proveedores';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('=========================================================');
        $this->info(' SINCRONIZACIÓN DE CONFIGURACIONES DE PROVEEDORES');
        $this->info('=========================================================');

        $configsDir = app_path('SupplierConfigs');
        if (!File::isDirectory($configsDir)) {
            File::makeDirectory($configsDir, 0755, true);
        }

        $suppliers = Supplier::with('connection')->get();
        $this->line("• Total de proveedores encontrados: <fg=yellow>{$suppliers->count()}</>");

        $syncedCount = 0;

        foreach ($suppliers as $supplier) {
            $connection = $supplier->connection;
            if (!$connection) {
                continue;
            }

            $host = strtolower($connection->host ?? '');
            $supplierName = strtolower($supplier->name ?? '');

            // Plantilla para Cristmedicals
            if (str_contains($host, 'cristmedicals') || str_contains($supplierName, 'crist')) {
                $content = $this->getCristmedicalsTemplate();
                $this->writeConfigFiles($configsDir, (string) $supplier->id, [
                    'cristalmedicals',
                    'cristmedicals',
                ], $content);
                $syncedCount++;
                $this->info("✓ Sincronizado Cristmedicals -> ID {$supplier->id}");
            }

            // Plantilla para Cobeca / Mafarta
            if (str_contains($host, 'cobeca') || str_contains($supplierName, 'mafarta') || str_contains($supplierName, 'cobeca')) {
                $content = $this->getCobecaTemplate();
                $this->writeConfigFiles($configsDir, (string) $supplier->id, [
                    'mafarta',
                    'cobeca',
                    'drogueriascobeca',
                ], $content);
                $syncedCount++;
                $this->info("✓ Sincronizado Cobeca/Mafarta -> ID {$supplier->id}");
            }
        }

        $this->info('=========================================================');
        $this->info("Sincronización completada. ({$syncedCount} proveedores configurados)");
        $this->info('=========================================================');

        return self::SUCCESS;
    }

    /**
     * Escribe la configuración para el ID numérico y sus slugs de alias.
     */
    private function writeConfigFiles(string $dir, string $id, array $aliases, string $content): void
    {
        // Archivo por ID numérico
        $idPath = "{$dir}/{$id}.php";
        File::put($idPath, $content);

        // Archivos por alias / slugs
        foreach ($aliases as $alias) {
            $slug = Str::slug($alias, '');
            if (!empty($slug)) {
                $aliasPath = "{$dir}/{$slug}.php";
                File::put($aliasPath, $content);
            }
        }
    }

    /**
     * Plantilla de configuración para Cristmedicals.
     */
    private function getCristmedicalsTemplate(): string
    {
        return <<<'PHP'
<?php

return [
    'productos' => function ($connection) {
        $co_cli = !empty($connection->username) ? $connection->username : 'FAR00818';
        return [
            'url' => 'https://apienterprise.cristmedicals.com/api/v1/articulos?co_cli=' . urlencode($co_cli),
            'method' => 'get',
        ];
    },
    'facturas' => function ($connection) {
        $co_cli = !empty($connection->username) ? $connection->username : 'FAR00818';
        return [
            'url' => 'https://apienterprise.cristmedicals.com/api/v1/facturas?co_cli=' . urlencode($co_cli),
            'method' => 'get',
        ];
    },
    'factura_detalle' => function ($connection, $facturaId) {
        // Viene anidado en la respuesta de facturas
        return [];
    },
];
PHP;
    }

    /**
     * Plantilla de configuración para Droguerías Cobeca.
     */
    private function getCobecaTemplate(): string
    {
        return <<<'PHP'
<?php

return [
    'productos' => function ($connection) {
        return [
            'Drogueria' => 3,
        ];
    },
    'facturas' => function ($connection) {
        return [
            'fechaInicio' => '2025-08-01T23:39:32.886Z',
            'fechaFin' => now()->toIso8601String(),
            'cliente' => 31373,
            'drogueria' => 3,
        ];
    },
    'factura_detalle' => function ($connection, $facturaId) {
        return [
            'url' => 'https://comparadores.drogueriascobeca.com/api/facturas/detalle?cod_factura=' . $facturaId,
        ];
    },
];
PHP;
    }
}
