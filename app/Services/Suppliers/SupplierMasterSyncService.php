<?php

declare(strict_types=1);

namespace App\Services\Suppliers;

use App\Models\Supplier;
use App\Models\SupplierConnection;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class SupplierMasterSyncService
{
    /**
     * Resuelve el nombre de la conexión a la base de datos Master.
     */
    public function getMasterConnectionName(): string
    {
        try {
            if (Schema::connection('master_catalog')->hasTable('suppliers')) {
                return 'master_catalog';
            }
        } catch (\Throwable $e) {
            // Ignorar y continuar con fallback central
        }

        $central = config('tenancy.database.central_connection', 'mysql');
        if (Schema::connection($central)->hasTable('suppliers')) {
            return $central;
        }

        return DB::getDefaultConnection();
    }

    /**
     * Obtiene el listado de proveedores del Master cruzado con el estado local del Tenant.
     *
     * @param array<string, mixed> $filters
     * @return array<string, mixed>
     */
    public function getMasterCatalog(array $filters = []): array
    {
        $masterConn = $this->getMasterConnectionName();

        if (!Schema::connection($masterConn)->hasTable('suppliers')) {
            return [
                'data' => [],
                'total' => 0,
                'page' => 1,
                'per_page' => 25,
            ];
        }

        $masterColumns = Schema::connection($masterConn)->getColumnListing('suppliers');
        $desiredColumns = [
            'id',
            'name',
            'logo_url',
            'social_reason',
            'rif',
            'address',
            'sales_phone',
            'collections_phone',
            'payment_email',
            'type',
            'business_types',
            'credit_days',
            'is_indexed',
            'is_fiscal',
            'is_active',
            'created_at',
        ];
        $selectColumns = array_values(array_intersect($desiredColumns, $masterColumns));

        $query = DB::connection($masterConn)->table('suppliers')
            ->select($selectColumns);

        // Filtro de búsqueda (incluye RIF limpio sin guiones/espacios)
        $search = trim((string) ($filters['search'] ?? ''));
        if (!empty($search)) {
            $cleanSearchRif = strtoupper((string) preg_replace('/[^A-Za-z0-9]/', '', $search));
            $query->where(function ($q) use ($search, $cleanSearchRif) {
                $q->where('name', 'LIKE', "%{$search}%")
                    ->orWhere('rif', 'LIKE', "%{$search}%")
                    ->orWhere('social_reason', 'LIKE', "%{$search}%");
                if ($cleanSearchRif !== '') {
                    $q->orWhereRaw("UPPER(REPLACE(REPLACE(REPLACE(rif, '-', ''), ' ', ''), '.', '')) LIKE ?", ["%{$cleanSearchRif}%"]);
                }
            });
        }

        // Filtro por tipo de proveedor ('all' retorna todos los tipos)
        $supplierType = $filters['type'] ?? 'drogueria';
        if (!empty($supplierType) && $supplierType !== 'all') {
            $query->where(function ($q) use ($supplierType) {
                $q->where('type', $supplierType)->orWhereNull('type');
            });
        }

        $masterSuppliers = $query->orderBy('name', 'asc')->get();

        if ($masterSuppliers->isEmpty()) {
            return [
                'data' => [],
                'total' => 0,
                'page' => 1,
                'per_page' => 25,
            ];
        }

        // Obtener conexiones maestras
        $masterSupplierIds = $masterSuppliers->pluck('id')->all();
        $masterConnections = collect();

        if (Schema::connection($masterConn)->hasTable('supplier_connections')) {
            $masterConnections = DB::connection($masterConn)->table('supplier_connections')
                ->whereIn('supplier_id', $masterSupplierIds)
                ->where('type', 'NOT LIKE', '%bot%')
                ->select(['id', 'supplier_id', 'type', 'structure', 'secondary_structure', 'tertiary_structure'])
                ->get()
                ->groupBy('supplier_id');
        }

        // Obtener proveedores locales del tenant actual sin disparar appends ni N+1
        $localSuppliersByRif = [];
        $localSuppliersById = [];
        $localConnectionsBySupId = collect();

        if (Schema::hasTable('suppliers')) {
            $localQuery = DB::table('suppliers')->select(['id', 'rif', 'is_active', 'credit_days']);
            if (Schema::hasColumn('suppliers', 'deleted_at')) {
                $localQuery->whereNull('deleted_at');
            }
            $localSuppliers = $localQuery->get();

            foreach ($localSuppliers as $sup) {
                $localSuppliersById[(int) $sup->id] = $sup;
                if (!empty($sup->rif)) {
                    $cleanLocalRif = strtoupper((string) preg_replace('/[^A-Za-z0-9]/', '', trim((string) $sup->rif)));
                    if ($cleanLocalRif !== '') {
                        $localSuppliersByRif[$cleanLocalRif] = $sup;
                    }
                }
            }

            if (Schema::hasTable('supplier_connections')) {
                $localConnectionsBySupId = DB::table('supplier_connections')
                    ->select(['supplier_id', 'type', 'username', 'credit_days_email'])
                    ->get()
                    ->groupBy('supplier_id');
            }
        }

        // Cruzar datos y construir colección
        $results = $masterSuppliers->map(function ($masterSup) use (
            $masterConnections,
            $localSuppliersById,
            $localSuppliersByRif,
            $localConnectionsBySupId
        ) {
            $cleanRif = !empty($masterSup->rif)
                ? strtoupper((string) preg_replace('/[^A-Za-z0-9]/', '', trim((string) $masterSup->rif)))
                : null;

            // Identificar coincidencia local por ID oficial o por RIF normalizado
            $localSup = $localSuppliersById[(int) $masterSup->id]
                ?? ($cleanRif && isset($localSuppliersByRif[$cleanRif]) ? $localSuppliersByRif[$cleanRif] : null);

            $isInstalled = $localSup !== null;
            $isActive = $localSup ? (bool) ($localSup->is_active ?? true) : false;
            $tenantSupplierId = $localSup?->id ? (int) $localSup->id : null;

            // Extraer conexiones del master (excluyendo bots de scraping)
            $supConnections = $masterConnections->get($masterSup->id, collect())
                ->filter(fn ($c) => !str_contains((string) ($c->type ?? ''), 'bot'));
            $allowedConnectionTypes = $supConnections
                ->pluck('type')
                ->map(fn ($t) => strtolower(trim((string) $t)))
                ->filter()
                ->unique()
                ->values()
                ->all();
            if (empty($allowedConnectionTypes)) {
                $allowedConnectionTypes = ['file'];
            }

            $primaryConn = $supConnections->firstWhere('type', 'file')
                ?? $supConnections->firstWhere('type', 'email')
                ?? $supConnections->first();

            $integrationType = $primaryConn?->type ?? 'file';

            $rawStructure = $primaryConn?->structure;
            $structureArray = is_string($rawStructure) ? json_decode($rawStructure, true) : (array) $rawStructure;
            $hasExcelStructure = !empty($structureArray) && is_array($structureArray);

            // Resumen de columnas configuradas en el Master
            $columnsSummary = [];
            if ($hasExcelStructure) {
                foreach ($structureArray as $key => $val) {
                    if (is_string($val) && !empty($val)) {
                        $columnsSummary[$key] = $val;
                    }
                }
            }

            // Correo remitente configurado localmente en el tenant
            $localConns = $localSup ? $localConnectionsBySupId->get($localSup->id, collect()) : collect();
            $localFileConn = $localConns->firstWhere('type', 'file')
                ?? $localConns->firstWhere('type', 'email')
                ?? $localConns->first();

            $configuredEmail = $localFileConn?->username ?: ($localFileConn?->credit_days_email ?: null);

            $businessTypes = [];
            if (!empty($masterSup->business_types)) {
                $businessTypes = is_string($masterSup->business_types)
                    ? (json_decode($masterSup->business_types, true) ?: [])
                    : (is_array($masterSup->business_types) ? $masterSup->business_types : []);
            }

            return [
                'master_id' => (int) $masterSup->id,
                'name' => $masterSup->name,
                'logo_url' => $masterSup->logo_url ?? null,
                'social_reason' => $masterSup->social_reason ?? $masterSup->name,
                'rif' => $masterSup->rif,
                'address' => $masterSup->address ?? null,
                'sales_phone' => $masterSup->sales_phone ?? null,
                'collections_phone' => $masterSup->collections_phone ?? null,
                'payment_email' => $masterSup->payment_email ?? null,
                'type' => $masterSup->type ?? 'drogueria',
                'business_types' => $businessTypes,
                'is_indexed' => (bool) ($masterSup->is_indexed ?? false),
                'is_fiscal' => isset($masterSup->is_fiscal) ? (bool) $masterSup->is_fiscal : true,
                'integration_type' => $integrationType,
                'allowed_connection_types' => $allowedConnectionTypes,
                'has_excel_structure' => $hasExcelStructure,
                'excel_columns_summary' => $columnsSummary,
                'is_installed' => $isInstalled,
                'is_active' => $isActive,
                'tenant_supplier_id' => $tenantSupplierId,
                'configured_email' => $configuredEmail,
                'credit_days' => $localSup?->credit_days ?? $masterSup->credit_days ?? 0,
            ];
        });

        // Filtrar por estado si se solicita
        $statusFilter = $filters['status'] ?? 'all';
        if ($statusFilter === 'active') {
            $results = $results->filter(fn ($item) => $item['is_active'] === true);
        } elseif ($statusFilter === 'inactive') {
            $results = $results->filter(fn ($item) => $item['is_installed'] && !$item['is_active']);
        } elseif ($statusFilter === 'available') {
            $results = $results->filter(fn ($item) => !$item['is_installed'] || !$item['is_active']);
        }

        $total = $results->count();
        $page = max(1, (int) ($filters['page'] ?? 1));
        $perPage = max(5, min(100, (int) ($filters['per_page'] ?? 25)));

        $pagedItems = $results->forPage($page, $perPage)->values()->all();

        return [
            'data' => $pagedItems,
            'total' => $total,
            'page' => $page,
            'per_page' => $perPage,
        ];
    }

    /**
     * Activa un proveedor del Master en el Tenant local.
     * Copia las estructuras de Excel y asigna el correo electrónico del remitente.
     *
     * @param int $masterSupplierId
     * @param string|null $senderEmail
     * @param int|null $creditDays
     * @param bool $isActive
     * @return array<string, mixed>
     */
    public function activateMasterSupplier(
        int $masterSupplierId,
        ?string $senderEmail = null,
        ?int $creditDays = null,
        bool $isActive = true
    ): array {
        $masterConn = $this->getMasterConnectionName();

        $masterSup = DB::connection($masterConn)->table('suppliers')
            ->where('id', $masterSupplierId)
            ->first();

        if (!$masterSup) {
            throw new \InvalidArgumentException("No se encontró el proveedor #{$masterSupplierId} en el Catálogo Maestro.");
        }

        // Buscar si ya existe localmente en el tenant (incluyendo eliminados para restaurarlos)
        $localSup = Supplier::withTrashed()->where('id', $masterSupplierId)->first();
        if (!$localSup && !empty($masterSup->rif)) {
            $localSup = Supplier::withTrashed()->where('rif', trim($masterSup->rif))->first();
        }

        $cleanEmail = !empty($senderEmail) ? trim($senderEmail) : null;

        DB::beginTransaction();

        try {
            if ($localSup) {
                if (method_exists($localSup, 'trashed') && $localSup->trashed()) {
                    $localSup->restore();
                }

                // Actualizar identidad, logotipo oficial del Master y activación
                $updateData = [
                    'name' => $masterSup->name,
                    'logo_url' => $masterSup->logo_url ?? $localSup->logo_url,
                    'social_reason' => $masterSup->social_reason,
                    'rif' => $masterSup->rif,
                    'is_active' => $isActive,
                ];

                if ($creditDays !== null) {
                    $updateData['credit_days'] = max(0, $creditDays);
                }

                $localSup->update($updateData);
            } else {
                // Crear proveedor local manteniendo ID del master si está disponible
                $payload = [
                    'id' => $masterSup->id,
                    'name' => $masterSup->name,
                    'logo_url' => $masterSup->logo_url ?? null,
                    'social_reason' => $masterSup->social_reason,
                    'rif' => $masterSup->rif,
                    'type' => $masterSup->type ?? 'drogueria',
                    'credit_days' => $creditDays !== null ? max(0, $creditDays) : ($masterSup->credit_days ?? 0),
                    'is_active' => $isActive,
                ];

                // Si ya existe otro registro con ese ID exacto (incluso eliminado), dejar que auto-incremente
                if (Supplier::withTrashed()->where('id', $masterSup->id)->exists()) {
                    unset($payload['id']);
                }

                $localSup = Supplier::create($payload);
            }

            $this->syncMasterConnectionsToLocalSupplier(
                localSup: $localSup,
                masterSupplierId: $masterSupplierId,
                senderEmail: $cleanEmail,
                masterConn: $masterConn,
                createFallbackFileConnection: true
            );

            DB::commit();

            return [
                'success' => true,
                'message' => "Proveedor {$localSup->name} activado con éxito con la plantilla oficial del Master.",
                'supplier' => $localSup->fresh('connections'),
            ];
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error("Error activando proveedor #{$masterSupplierId} desde el Master: " . $e->getMessage(), [
                'exception' => $e,
            ]);
            throw $e;
        }
    }

    /**
     * Clona las plantillas de conexión (Excel, FTP, API, Bot) desde el Catálogo Maestro
     * hacia un proveedor local del tenant sin sobreescribir credenciales privadas existentes.
     */
    public function syncMasterConnectionsToLocalSupplier(
        Supplier $localSup,
        int $masterSupplierId,
        ?string $senderEmail = null,
        ?string $masterConn = null,
        bool $createFallbackFileConnection = false
    ): int {
        if (!Schema::hasTable('supplier_connections')) {
            return 0;
        }

        $masterConn = $masterConn ?: $this->getMasterConnectionName();
        $cleanEmail = !empty($senderEmail) ? trim($senderEmail) : null;

        $masterConnections = collect();
        if (Schema::connection($masterConn)->hasTable('supplier_connections')) {
            $masterConnections = DB::connection($masterConn)->table('supplier_connections')
                ->where('supplier_id', $masterSupplierId)
                ->where('type', 'NOT LIKE', '%bot%')
                ->get();
        }

        // Si el master no tiene conexiones registradas, opcionalmente crear base tipo Excel
        if ($masterConnections->isEmpty()) {
            if (!$createFallbackFileConnection && empty($cleanEmail)) {
                return 0;
            }

            $masterConnections = collect([(object) [
                'type' => 'file',
                'host' => $localSup->name . ' (Excel)',
                'has_header' => false,
                'pasv' => false,
                'structure' => null,
                'secondary_structure' => null,
                'tertiary_structure' => null,
                'invoice_structure' => null,
            ]]);
        }

        $syncedCount = 0;

        foreach ($masterConnections as $mConn) {
            $type = $mConn->type ?? 'file';
            $existingLocalConn = $localSup->connections()->where('type', $type)->first();

            $rawStructure = $mConn->structure ?? null;
            $struct = is_string($rawStructure) ? json_decode($rawStructure, true) : (array) $rawStructure;

            $rawSec = $mConn->secondary_structure ?? null;
            $secStruct = is_string($rawSec) ? json_decode($rawSec, true) : (array) $rawSec;

            $rawTert = $mConn->tertiary_structure ?? null;
            $tertStruct = is_string($rawTert) ? json_decode($rawTert, true) : (array) $rawTert;

            $rawInv = $mConn->invoice_structure ?? null;
            $invStruct = is_string($rawInv) ? json_decode($rawInv, true) : (array) $rawInv;

            if ($existingLocalConn) {
                $connUpdate = [
                    'structure' => $struct ?: $existingLocalConn->structure,
                    'secondary_structure' => $secStruct ?: $existingLocalConn->secondary_structure,
                    'tertiary_structure' => $tertStruct ?: $existingLocalConn->tertiary_structure,
                    'invoice_structure' => $invStruct ?: $existingLocalConn->invoice_structure,
                    'has_header' => (bool) ($mConn->has_header ?? $existingLocalConn->has_header),
                ];

                if (isset($mConn->credit_days_cod_col) && empty($existingLocalConn->credit_days_cod_col)) {
                    $connUpdate['credit_days_cod_col'] = $mConn->credit_days_cod_col;
                }
                if (isset($mConn->credit_days_col) && empty($existingLocalConn->credit_days_col)) {
                    $connUpdate['credit_days_col'] = $mConn->credit_days_col;
                }

                if (in_array($type, ['file', 'email'], true) && !empty($cleanEmail)) {
                    $connUpdate['username'] = $cleanEmail;
                    $connUpdate['host'] = $localSup->name . ' (Excel)';
                }

                $existingLocalConn->update($connUpdate);
                $syncedCount++;
            } else {
                $newConnData = [
                    'supplier_id' => $localSup->id,
                    'type' => $type,
                    'host' => in_array($type, ['file', 'email'], true) ? ($localSup->name . ' (Excel)') : ($mConn->host ?? null),
                    'port' => $mConn->port ?? null,
                    'username' => in_array($type, ['file', 'email'], true) ? $cleanEmail : null,
                    'password' => null,
                    'path' => null,
                    'pasv' => (bool) ($mConn->pasv ?? true),
                    'has_header' => (bool) ($mConn->has_header ?? false),
                    'structure' => $struct ?: null,
                    'secondary_structure' => $secStruct ?: null,
                    'tertiary_structure' => $tertStruct ?: null,
                    'invoice_structure' => $invStruct ?: null,
                    'credit_days_cod_col' => $mConn->credit_days_cod_col ?? null,
                    'credit_days_col' => $mConn->credit_days_col ?? null,
                ];

                $localSup->connections()->create($newConnData);
                $syncedCount++;
            }
        }

        return $syncedCount;
    }

    /**
     * Promueve la estructura técnica configurada por un tenant hacia tovaerp_master
     * para alimentar el centro de datos colaborativo sin exponer credenciales locales.
     */
    public function promoteLocalConnectionToMaster(Supplier $supplier, SupplierConnection $connection): void
    {
        $isTenant = function_exists('tenancy') && tenancy()->initialized;
        if (!$isTenant) {
            return;
        }

        $typeValue = $supplier->type instanceof \App\Enums\SupplierType
            ? $supplier->type->value
            : (string) ($supplier->type ?? 'drogueria');

        if ($typeValue === 'externo') {
            return;
        }

        try {
            $masterClient = app(\App\Services\Catalog\MasterCatalogClientService::class);
            $masterSupplierId = null;

            if (!empty($supplier->rif)) {
                $masterSup = $masterClient->findMasterSupplierByRif($supplier->rif);
                if (!empty($masterSup['id'])) {
                    $masterSupplierId = (int) $masterSup['id'];
                }
            }

            if (!$masterSupplierId) {
                $registered = $masterClient->registerSupplierInMaster([
                    'name' => $supplier->name,
                    'social_reason' => $supplier->social_reason,
                    'rif' => $supplier->rif,
                    'address' => $supplier->address,
                    'sales_phone' => $supplier->sales_phone,
                    'collections_phone' => $supplier->collections_phone,
                    'payment_email' => $supplier->payment_email,
                    'type' => $typeValue,
                    'business_types' => $supplier->business_types,
                    'is_indexed' => $supplier->is_indexed,
                    'is_fiscal' => $supplier->is_fiscal,
                ]);
                $masterSupplierId = !empty($registered['id']) ? (int) $registered['id'] : null;
            }

            if (!$masterSupplierId) {
                return;
            }

            $masterClient->promoteConnectionStructureToMaster(
                masterSupplierId: $masterSupplierId,
                connectionData: [
                    'type' => $connection->type,
                    'host' => $connection->host,
                    'port' => $connection->port,
                    'pasv' => $connection->pasv,
                    'has_header' => $connection->has_header,
                    'structure' => $connection->structure,
                    'secondary_structure' => $connection->secondary_structure,
                    'tertiary_structure' => $connection->tertiary_structure,
                    'invoice_structure' => $connection->invoice_structure,
                    'credit_days_cod_col' => $connection->credit_days_cod_col,
                    'credit_days_col' => $connection->credit_days_col,
                ],
                supplierName: $supplier->name
            );
        } catch (\Throwable $e) {
            Log::warning("No se pudo promover estructura de conexión al Master para proveedor {$supplier->name}: " . $e->getMessage());
        }
    }
}
