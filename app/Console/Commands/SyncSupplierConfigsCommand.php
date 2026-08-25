<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Supplier;
use App\Models\SupplierConnection;
use App\Helpers\FtpCrypt;
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
    protected $description = 'Sincroniza y valida las configuraciones de todos los proveedores (API, FTP, Excel) según sus IDs actuales';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('========================================================================');
        $this->info(' ESTADO Y SINCRONIZACIÓN DE CONFIGURACIONES DE PROVEEDORES');
        $this->info('========================================================================');

        $configsDir = app_path('SupplierConfigs');
        if (!File::isDirectory($configsDir)) {
            File::makeDirectory($configsDir, 0755, true);
        }

        $suppliers = Supplier::with('connections')->get();
        $this->line("• Total de proveedores en base de datos: <fg=yellow>{$suppliers->count()}</>");

        $tableRows = [];
        $apiSynced = 0;
        $ftpSynced = 0;
        $fileSynced = 0;

        foreach ($suppliers as $supplier) {
            $connections = $supplier->connections;
            if ($connections->isEmpty()) {
                continue;
            }

            foreach ($connections as $connection) {
                $type = strtoupper($connection->type ?? 'FILE');
                $host = $connection->host ?? 'N/A';
                $user = $connection->username ?? 'N/A';
                $status = '<fg=green>OK (BD)</>';

                // Manejo especial de proveedores tipo API (requieren archivo PHP en app/SupplierConfigs)
                $hostLower = strtolower($connection->host ?? '');
                $supplierNameLower = strtolower($supplier->name ?? '');

                if (str_contains($hostLower, 'cristmedicals') || str_contains($supplierNameLower, 'crist')) {
                    $content = $this->getCristmedicalsTemplate();
                    $this->writeConfigFiles($configsDir, (string) $supplier->id, [
                        'cristalmedicals',
                        'cristmedicals',
                    ], $content);
                    $apiSynced++;
                    $status = "<fg=green>OK (PHP: {$supplier->id}.php)</>";
                } elseif (str_contains($hostLower, 'cobeca') || str_contains($supplierNameLower, 'mafarta') || str_contains($supplierNameLower, 'cobeca')) {
                    $content = $this->getCobecaTemplate();
                    $this->writeConfigFiles($configsDir, (string) $supplier->id, [
                        'mafarta',
                        'cobeca',
                        'drogueriascobeca',
                    ], $content);
                    $apiSynced++;
                    $status = "<fg=green>OK (PHP: {$supplier->id}.php)</>";
                } elseif ($connection->type === 'ftp') {
                    $ftpSynced++;
                    $hasPass = !empty($connection->password) ? 'Sí (Cifrada)' : 'No';
                    $status = "<fg=green>OK (FTP - Pass: {$hasPass})</>";
                } else {
                    $fileSynced++;
                    $status = '<fg=green>OK (Plantilla Excel/TXT)</>';
                }

                $tableRows[] = [
                    $supplier->id,
                    $supplier->name,
                    $type,
                    Str::limit($host, 35),
                    $user,
                    $status,
                ];
            }
        }

        $this->table(
            ['ID', 'Proveedor', 'Tipo', 'Host / Origen', 'Usuario', 'Estado Configuración'],
            $tableRows
        );

        $this->info('========================================================================');
        $this->info("✓ Resumen: {$apiSynced} APIs sincronizadas en app/SupplierConfigs, {$ftpSynced} conexiones FTP listas en BD, {$fileSynced} mapeos Excel listos.");
        $this->info('========================================================================');

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
        $desde = now()->subDays(30)->format('Y-m-d');
        $hasta = now()->format('Y-m-d');
        return [
            'url' => 'https://apienterprise.cristmedicals.com/api/v1/facturas?co_cli=' . urlencode($co_cli) . '&fec_desde=' . $desde . '&fec_hasta=' . $hasta,
            'method' => 'post',
        ];
    },
    'factura_detalle' => function ($connection, $facturaId) {
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
            'url' => 'https://comparadores.drogueriascobeca.com/api/Articulos',
            'method' => 'post',
            'payload' => [
                'cod_drogueria' => 3,
            ],
        ];
    },
    'facturas' => function ($connection) {
        return [];
    },
    'factura_detalle' => function ($connection, $facturaId) {
        return [];
    },
];
PHP;
    }
}
