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

        $query = DB::connection($masterConn)->table('suppliers')
            ->select(['id', 'name', 'social_reason', 'rif', 'type', 'credit_days', 'is_active', 'created_at']);

        // Filtro de búsqueda
        $search = trim((string) ($filters['search'] ?? ''));
        if (!empty($search)) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'LIKE', "%{$search}%")
                    ->orWhere('rif', 'LIKE', "%{$search}%")
                    ->orWhere('social_reason', 'LIKE', "%{$search}%");
            });
        }

        // Filtro por tipo de proveedor (por defecto droguería o null)
        $supplierType = $filters['type'] ?? 'drogueria';
        if (!empty($supplierType)) {
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
                ->get()
                ->groupBy('supplier_id');
        }

        // Obtener proveedores locales del tenant actual
        $localSuppliersByRif = [];
        $localSuppliersById = [];
        $localConnectionsBySupId = collect();

        if (Schema::hasTable('suppliers')) {
            $localSuppliers = Supplier::all();
            foreach ($localSuppliers as $sup) {
                $localSuppliersById[$sup->id] = $sup;
                if (!empty($sup->rif)) {
                    $localSuppliersByRif[strtoupper(trim($sup->rif))] = $sup;
                }
            }

            if (Schema::hasTable('supplier_connections')) {
                $localConnectionsBySupId = SupplierConnection::all()->groupBy('supplier_id');
            }
        }

        // Cruzar datos y construir colección
        $results = $masterSuppliers->map(function ($masterSup) use (
            $masterConnections,
            $localSuppliersById,
            $localSuppliersByRif,
            $localConnectionsBySupId
        ) {
            $cleanRif = !empty($masterSup->rif) ? strtoupper(trim($masterSup->rif)) : null;

            // Identificar coincidencia local
            $localSup = $localSuppliersById[$masterSup->id]
                ?? ($cleanRif && isset($localSuppliersByRif[$cleanRif]) ? $localSuppliersByRif[$cleanRif] : null);

            $isInstalled = $localSup !== null;
            $isActive = $localSup ? (bool) $localSup->is_active : false;
            $tenantSupplierId = $localSup?->id;

            // Extraer conexiones del master
            $supConnections = $masterConnections->get($masterSup->id, collect());
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

            return [
                'master_id' => (int) $masterSup->id,
                'name' => $masterSup->name,
                'social_reason' => $masterSup->social_reason,
                'rif' => $masterSup->rif,
                'type' => $masterSup->type ?? 'drogueria',
                'integration_type' => $integrationType,
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

        // Buscar si ya existe localmente en el tenant por ID o por RIF
        $localSup = Supplier::where('id', $masterSupplierId)->first();
        if (!$localSup && !empty($masterSup->rif)) {
            $localSup = Supplier::where('rif', trim($masterSup->rif))->first();
        }

        $cleanEmail = !empty($senderEmail) ? trim($senderEmail) : null;

        DB::beginTransaction();

        try {
            if ($localSup) {
                // Actualizar identidad y activación
                $updateData = [
                    'name' => $masterSup->name,
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
                    'social_reason' => $masterSup->social_reason,
                    'rif' => $masterSup->rif,
                    'type' => $masterSup->type ?? 'drogueria',
                    'credit_days' => $creditDays !== null ? max(0, $creditDays) : ($masterSup->credit_days ?? 0),
                    'is_active' => $isActive,
                ];

                // Si ya existe otro registro con ese ID exacto, dejar que auto-incremente
                if (Supplier::where('id', $masterSup->id)->exists()) {
                    unset($payload['id']);
                }

                $localSup = Supplier::create($payload);
            }

            // Obtener conexiones maestras del proveedor
            $masterConnections = collect();
            if (Schema::connection($masterConn)->hasTable('supplier_connections')) {
                $masterConnections = DB::connection($masterConn)->table('supplier_connections')
                    ->where('supplier_id', $masterSupplierId)
                    ->get();
            }

            // Si el master no tiene conexiones registradas para este proveedor, crear una base tipo Excel
            if ($masterConnections->isEmpty()) {
                $masterConnections = collect([(object) [
                    'type' => 'file',
                    'host' => $masterSup->name . ' (Excel)',
                    'has_header' => false,
                    'pasv' => false,
                    'structure' => null,
                    'secondary_structure' => null,
                    'tertiary_structure' => null,
                ]]);
            }

            foreach ($masterConnections as $mConn) {
                $type = $mConn->type ?? 'file';

                $existingLocalConn = $localSup->connections()->where('type', $type)->first();

                $rawStructure = $mConn->structure ?? null;
                $struct = is_string($rawStructure) ? json_decode($rawStructure, true) : (array) $rawStructure;

                $rawSec = $mConn->secondary_structure ?? null;
                $secStruct = is_string($rawSec) ? json_decode($rawSec, true) : (array) $rawSec;

                $rawTert = $mConn->tertiary_structure ?? null;
                $tertStruct = is_string($rawTert) ? json_decode($rawTert, true) : (array) $rawTert;

                if ($existingLocalConn) {
                    $connUpdate = [
                        'structure' => $struct ?: $existingLocalConn->structure,
                        'secondary_structure' => $secStruct ?: $existingLocalConn->secondary_structure,
                        'tertiary_structure' => $tertStruct ?: $existingLocalConn->tertiary_structure,
                        'has_header' => (bool) ($mConn->has_header ?? $existingLocalConn->has_header),
                    ];

                    if (in_array($type, ['file', 'email']) && !empty($cleanEmail)) {
                        $connUpdate['username'] = $cleanEmail;
                        $connUpdate['host'] = $masterSup->name . ' (Excel)';
                    }

                    $existingLocalConn->update($connUpdate);
                } else {
                    $newConnData = [
                        'supplier_id' => $localSup->id,
                        'type' => $type,
                        'host' => in_array($type, ['file', 'email']) ? ($masterSup->name . ' (Excel)') : ($mConn->host ?? null),
                        'port' => $mConn->port ?? null,
                        'username' => in_array($type, ['file', 'email']) ? $cleanEmail : null,
                        'password' => null,
                        'path' => null,
                        'pasv' => (bool) ($mConn->pasv ?? true),
                        'has_header' => (bool) ($mConn->has_header ?? false),
                        'structure' => $struct ?: null,
                        'secondary_structure' => $secStruct ?: null,
                        'tertiary_structure' => $tertStruct ?: null,
                    ];

                    $localSup->connections()->create($newConnData);
                }
            }

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
}
