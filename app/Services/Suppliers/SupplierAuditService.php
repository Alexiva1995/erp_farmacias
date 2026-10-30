<?php

declare(strict_types=1);

namespace App\Services\Suppliers;

use App\Contracts\Suppliers\SupplierAuditServiceInterface;
use App\Models\Tenant;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class SupplierAuditService implements SupplierAuditServiceInterface
{
    /**
     * Obtiene la auditoría de un proveedor cruzando todos los tenants registrados
     */
    public function getSupplierMultiTenantAudit(?int $supplierId = null, ?string $month = null): array
    {
        $targetSupplierId = $supplierId ?: 1016; // Vickmedicals por defecto

        if (!empty($month) && preg_match('/^\d{4}-\d{2}$/', $month)) {
            $parsedDate = Carbon::createFromFormat('Y-m', $month);
            $startOfMonth = $parsedDate->copy()->startOfMonth();
            $endOfMonth = $parsedDate->copy()->endOfMonth();
        } else {
            $startOfMonth = Carbon::now()->startOfMonth();
            $endOfMonth = Carbon::now()->endOfMonth();
        }

        $monthLabel = $startOfMonth->translatedFormat('F Y');

        $tenantRows = [];
        $tenants = Tenant::with('domains')->get();

        // 1. Recorrer tenants registrados en el gestor multi-tenant
        foreach ($tenants as $tenant) {
            try {
                $tenantDomain = $tenant->domains->first()?->domain ?? "{$tenant->id}.tovaerp.com";
                $tenantData = null;

                $tenant->run(function () use (&$tenantData, $targetSupplierId, $startOfMonth, $endOfMonth, $tenant, $tenantDomain) {
                    $tenantData = $this->extractDataForCurrentDatabase(
                        $targetSupplierId,
                        $startOfMonth,
                        $endOfMonth,
                        (string) $tenant->id,
                        $tenantDomain
                    );
                });

                if ($tenantData) {
                    $tenantRows[] = $tenantData;
                }
            } catch (\Throwable $e) {
                $tenantRows[] = [
                    'tenant_id' => (string) $tenant->id,
                    'tenant_name' => (string) $tenant->id,
                    'tenant_domain' => "{$tenant->id}.tovaerp.com",
                    'tenant_url' => "https://{$tenant->id}.tovaerp.com",
                    'supplier_found' => false,
                    'supplier_name' => 'Error de conexión',
                    'supplier_id' => null,
                    'is_active' => false,
                    'catalog_updates_month' => 0,
                    'catalog_updates_total' => 0,
                    'last_sync_date' => null,
                    'current_products' => 0,
                    'orders_month' => 0,
                    'orders_total' => 0,
                    'orders_month_usd' => 0.0,
                    'orders_total_usd' => 0.0,
                    'last_order_date' => null,
                    'invoices_month' => 0,
                    'invoices_total' => 0,
                    'invoices_month_usd' => 0.0,
                    'invoices_total_usd' => 0.0,
                    'last_invoice_date' => null,
                    'relationship_status' => 'error',
                    'relationship_label' => 'Error al conectar',
                    'relationship_color' => 'error',
                ];
            }
        }

        // 2. Si no hay tenants registrados en la tabla central (ej. entorno local de pruebas con BDs separadas)
        if (empty($tenantRows)) {
            $localDbs = ['farmaciabs', 'fama', 'kolo', 'victoria'];
            foreach ($localDbs as $dbName) {
                try {
                    $connKey = "audit_{$dbName}";
                    config(["database.connections.{$connKey}" => array_merge(
                        config('database.connections.mysql'),
                        ['database' => $dbName]
                    )]);

                    $tenantRows[] = $this->extractDataForConnection(
                        $connKey,
                        $targetSupplierId,
                        $startOfMonth,
                        $endOfMonth,
                        $dbName,
                        "{$dbName}.test"
                    );
                } catch (\Throwable) {
                    // Omitir si la base no existe localmente
                }
            }
        }

        // 3. Calcular métricas consolidadas (KPIs)
        $totalTenants = count($tenantRows);
        $buyingThisMonthCount = 0;
        $buyingHistoricalCount = 0;
        $consultingOnlyCount = 0;
        $inactiveCount = 0;

        $totalInvoicesMonthUsd = 0.0;
        $totalInvoicesTotalUsd = 0.0;
        $totalOrdersMonthCount = 0;
        $totalInvoicesMonthCount = 0;

        foreach ($tenantRows as $row) {
            $totalInvoicesMonthUsd += $row['invoices_month_usd'];
            $totalInvoicesTotalUsd += $row['invoices_total_usd'];
            $totalOrdersMonthCount += $row['orders_month'];
            $totalInvoicesMonthCount += $row['invoices_month'];

            switch ($row['relationship_status']) {
                case 'buying_now':
                    $buyingThisMonthCount++;
                    break;
                case 'historical_buyer':
                    $buyingHistoricalCount++;
                    break;
                case 'catalog_only':
                    $consultingOnlyCount++;
                    break;
                default:
                    $inactiveCount++;
                    break;
            }
        }

        // Nombre del proveedor seleccionado
        $targetSupplierName = 'VICKMEDICALS';
        if (Schema::hasTable('suppliers')) {
            $sup = DB::table('suppliers')->where('id', $targetSupplierId)->first();
            if ($sup && !empty($sup->name)) {
                $targetSupplierName = $sup->name;
            }
        }

        return [
            'period' => [
                'month' => $startOfMonth->format('Y-m'),
                'label' => $monthLabel,
                'start_date' => $startOfMonth->toDateString(),
                'end_date' => $endOfMonth->toDateString(),
            ],
            'supplier' => [
                'id' => $targetSupplierId,
                'name' => $targetSupplierName,
            ],
            'kpis' => [
                'total_tenants' => $totalTenants,
                'buying_now_count' => $buyingThisMonthCount,
                'historical_buyers_count' => $buyingHistoricalCount,
                'catalog_only_count' => $consultingOnlyCount,
                'inactive_count' => $inactiveCount,
                'orders_month_count' => $totalOrdersMonthCount,
                'invoices_month_count' => $totalInvoicesMonthCount,
                'invoices_month_usd' => round($totalInvoicesMonthUsd, 2),
                'invoices_total_usd' => round($totalInvoicesTotalUsd, 2),
            ],
            'tenants' => $tenantRows,
        ];
    }

    /**
     * Lista de droguerías disponibles para seleccionar en el dashboard
     */
    public function getAvailableSuppliers(): array
    {
        if (!Schema::hasTable('suppliers')) {
            return [
                ['id' => 1016, 'name' => 'VICKMEDICALS', 'rif' => 'J412279149'],
            ];
        }

        $suppliers = DB::table('suppliers')
            ->select(['id', 'name', 'rif', 'social_reason', 'is_active'])
            ->where(function ($q) {
                $q->where('type', 'drogueria')
                  ->orWhereNull('type')
                  ->orWhere('id', 1016);
            })
            ->orderBy('name', 'asc')
            ->get();

        return $suppliers->map(fn ($s) => [
            'id' => (int) $s->id,
            'name' => (string) $s->name,
            'rif' => (string) ($s->rif ?? ''),
            'is_active' => (bool) ($s->is_active ?? true),
        ])->toArray();
    }

    /**
     * Extrae métricas del proveedor en el contexto actual de la base de datos
     */
    private function extractDataForCurrentDatabase(
        int $targetSupplierId,
        Carbon $startOfMonth,
        Carbon $endOfMonth,
        string $tenantId,
        string $tenantDomain
    ): array {
        if (!Schema::hasTable('suppliers')) {
            return $this->createEmptyRow($tenantId, $tenantDomain, 'Tabla suppliers no existe');
        }

        // Búsqueda inteligente del proveedor por ID o por coincidencia de nombre/RIF
        $supplier = DB::table('suppliers')
            ->where('id', $targetSupplierId)
            ->first();

        if (!$supplier) {
            $supplier = DB::table('suppliers')
                ->where(function ($q) {
                    $q->where('name', 'LIKE', '%VICKMEDICAL%')
                      ->orWhere('social_reason', 'LIKE', '%VICKMEDICAL%')
                      ->orWhere('rif', 'LIKE', '%412279149%');
                })
                ->first();
        }

        if (!$supplier) {
            return $this->createEmptyRow($tenantId, $tenantDomain, 'No registrado');
        }

        $supId = (int) $supplier->id;
        $supName = (string) $supplier->name;
        $isActive = isset($supplier->is_active) ? (bool) $supplier->is_active : true;

        // 1. Sincronizaciones de catálogo
        $syncsMonth = 0;
        $syncsTotal = 0;
        $lastSyncDate = null;

        if (Schema::hasTable('supplier_connection_statuses')) {
            $syncsTotal = DB::table('supplier_connection_statuses')
                ->where('supplier_id', $supId)
                ->count();

            $syncsMonth = DB::table('supplier_connection_statuses')
                ->where('supplier_id', $supId)
                ->whereBetween('created_at', [$startOfMonth, $endOfMonth])
                ->count();

            $lastSyncDate = DB::table('supplier_connection_statuses')
                ->where('supplier_id', $supId)
                ->max('created_at');
        }

        // Productos vigentes
        $currentProducts = 0;
        if (Schema::hasTable('product_suppliers')) {
            $currentProducts = DB::table('product_suppliers')
                ->where('supplier_id', $supId)
                ->count();

            if (!$lastSyncDate) {
                $lastSyncDate = DB::table('product_suppliers')
                    ->where('supplier_id', $supId)
                    ->max('updated_at');
            }
        }

        if (!$lastSyncDate && Schema::hasTable('supplier_connections')) {
            $lastSyncDate = DB::table('supplier_connections')
                ->where('supplier_id', $supId)
                ->value('last_connection');
        }

        // 2. Pedidos en AutoOrder
        $ordersMonth = 0;
        $ordersTotal = 0;
        $ordersMonthUsd = 0.0;
        $ordersTotalUsd = 0.0;
        $lastOrderDate = null;

        if (Schema::hasTable('auto_orders')) {
            $ordersTotal = DB::table('auto_orders')
                ->where('supplier_id', $supId)
                ->count();

            $ordersTotalUsd = (float) DB::table('auto_orders')
                ->where('supplier_id', $supId)
                ->sum('total_amount');

            $monthOrderQuery = DB::table('auto_orders')
                ->where('supplier_id', $supId)
                ->where(function ($q) use ($startOfMonth, $endOfMonth) {
                    $q->whereBetween('order_date', [$startOfMonth, $endOfMonth])
                      ->orWhereBetween('created_at', [$startOfMonth, $endOfMonth]);
                });

            $ordersMonth = $monthOrderQuery->count();
            $ordersMonthUsd = (float) $monthOrderQuery->sum('total_amount');

            $lastOrderDate = DB::table('auto_orders')
                ->where('supplier_id', $supId)
                ->max('order_date')
                ?: DB::table('auto_orders')
                ->where('supplier_id', $supId)
                ->max('created_at');
        }

        // 3. Facturas de compra (Invoices)
        $invoicesMonth = 0;
        $invoicesTotal = 0;
        $invoicesMonthUsd = 0.0;
        $invoicesTotalUsd = 0.0;
        $lastInvoiceDate = null;

        if (Schema::hasTable('invoices')) {
            $invoicesTotal = DB::table('invoices')
                ->where('supplier_id', $supId)
                ->count();

            $invoicesTotalUsd = (float) DB::table('invoices')
                ->where('supplier_id', $supId)
                ->sum('total_usd');

            $monthInvoiceQuery = DB::table('invoices')
                ->where('supplier_id', $supId)
                ->where(function ($q) use ($startOfMonth, $endOfMonth) {
                    $q->whereBetween('created_invoice_date', [$startOfMonth, $endOfMonth])
                      ->orWhereBetween('received_date', [$startOfMonth, $endOfMonth])
                      ->orWhereBetween('created_at', [$startOfMonth, $endOfMonth]);
                });

            $invoicesMonth = $monthInvoiceQuery->count();
            $invoicesMonthUsd = (float) $monthInvoiceQuery->sum('total_usd');

            $lastInvoiceDate = DB::table('invoices')
                ->where('supplier_id', $supId)
                ->max('created_invoice_date')
                ?: DB::table('invoices')
                ->where('supplier_id', $supId)
                ->max('created_at');
        }

        // 4. Clasificación semántica de la relación comercial
        $statusKey = 'inactive';
        $statusLabel = 'Inactivo';
        $statusColor = 'secondary';

        if ($invoicesMonth > 0 || $ordersMonth > 0) {
            $statusKey = 'buying_now';
            $statusLabel = 'Comprando este mes';
            $statusColor = 'success';
        } elseif ($invoicesTotal > 0 || $ordersTotal > 0) {
            $statusKey = 'historical_buyer';
            $statusLabel = 'Comprador histórico';
            $statusColor = 'info';
        } elseif ($syncsTotal > 0 || $currentProducts > 0) {
            $statusKey = 'catalog_only';
            $statusLabel = 'Solo consulta catálogo';
            $statusColor = 'warning';
        }

        return [
            'tenant_id' => $tenantId,
            'tenant_name' => ucfirst($tenantId),
            'tenant_domain' => $tenantDomain,
            'tenant_url' => "https://{$tenantDomain}",
            'supplier_found' => true,
            'supplier_name' => $supName,
            'supplier_id' => $supId,
            'is_active' => $isActive,
            'catalog_updates_month' => $syncsMonth,
            'catalog_updates_total' => $syncsTotal,
            'last_sync_date' => $lastSyncDate ? Carbon::parse($lastSyncDate)->format('Y-m-d H:i') : null,
            'current_products' => $currentProducts,
            'orders_month' => $ordersMonth,
            'orders_total' => $ordersTotal,
            'orders_month_usd' => round($ordersMonthUsd, 2),
            'orders_total_usd' => round($ordersTotalUsd, 2),
            'last_order_date' => $lastOrderDate ? Carbon::parse($lastOrderDate)->format('Y-m-d') : null,
            'invoices_month' => $invoicesMonth,
            'invoices_total' => $invoicesTotal,
            'invoices_month_usd' => round($invoicesMonthUsd, 2),
            'invoices_total_usd' => round($invoicesTotalUsd, 2),
            'last_invoice_date' => $lastInvoiceDate ? Carbon::parse($lastInvoiceDate)->format('Y-m-d') : null,
            'relationship_status' => $statusKey,
            'relationship_label' => $statusLabel,
            'relationship_color' => $statusColor,
        ];
    }

    /**
     * Extrae métricas ejecutando la consulta sobre una conexión específica
     */
    private function extractDataForConnection(
        string $connKey,
        int $targetSupplierId,
        Carbon $startOfMonth,
        Carbon $endOfMonth,
        string $tenantId,
        string $tenantDomain
    ): array {
        $db = DB::connection($connKey);
        $schema = $db->getSchemaBuilder();

        if (!$schema->hasTable('suppliers')) {
            return $this->createEmptyRow($tenantId, $tenantDomain, 'No registrado');
        }

        $supplier = $db->table('suppliers')
            ->where('id', $targetSupplierId)
            ->first();

        if (!$supplier) {
            $supplier = $db->table('suppliers')
                ->where(function ($q) {
                    $q->where('name', 'LIKE', '%VICKMEDICAL%')
                      ->orWhere('social_reason', 'LIKE', '%VICKMEDICAL%')
                      ->orWhere('rif', 'LIKE', '%412279149%');
                })
                ->first();
        }

        if (!$supplier) {
            return $this->createEmptyRow($tenantId, $tenantDomain, 'No registrado');
        }

        $supId = (int) $supplier->id;
        $supName = (string) $supplier->name;
        $isActive = isset($supplier->is_active) ? (bool) $supplier->is_active : true;

        $syncsTotal = $schema->hasTable('supplier_connection_statuses')
            ? $db->table('supplier_connection_statuses')->where('supplier_id', $supId)->count() : 0;
        $syncsMonth = $schema->hasTable('supplier_connection_statuses')
            ? $db->table('supplier_connection_statuses')->where('supplier_id', $supId)->whereBetween('created_at', [$startOfMonth, $endOfMonth])->count() : 0;
        $lastSync = $schema->hasTable('supplier_connection_statuses')
            ? $db->table('supplier_connection_statuses')->where('supplier_id', $supId)->max('created_at') : null;

        $currentProducts = $schema->hasTable('product_suppliers')
            ? $db->table('product_suppliers')->where('supplier_id', $supId)->count() : 0;

        $ordersTotal = $schema->hasTable('auto_orders')
            ? $db->table('auto_orders')->where('supplier_id', $supId)->count() : 0;
        $ordersTotalUsd = $schema->hasTable('auto_orders')
            ? (float) $db->table('auto_orders')->where('supplier_id', $supId)->sum('total_amount') : 0.0;
        $ordersMonth = $schema->hasTable('auto_orders')
            ? $db->table('auto_orders')->where('supplier_id', $supId)->where(function ($q) use ($startOfMonth, $endOfMonth) {
                $q->whereBetween('order_date', [$startOfMonth, $endOfMonth])
                  ->orWhereBetween('created_at', [$startOfMonth, $endOfMonth]);
            })->count() : 0;
        $lastOrderDate = $schema->hasTable('auto_orders')
            ? ($db->table('auto_orders')->where('supplier_id', $supId)->max('order_date') ?: $db->table('auto_orders')->where('supplier_id', $supId)->max('created_at')) : null;

        $invoicesTotal = $schema->hasTable('invoices')
            ? $db->table('invoices')->where('supplier_id', $supId)->count() : 0;
        $invoicesTotalUsd = $schema->hasTable('invoices')
            ? (float) $db->table('invoices')->where('supplier_id', $supId)->sum('total_usd') : 0.0;
        $invoicesMonth = $schema->hasTable('invoices')
            ? $db->table('invoices')->where('supplier_id', $supId)->where(function ($q) use ($startOfMonth, $endOfMonth) {
                $q->whereBetween('created_invoice_date', [$startOfMonth, $endOfMonth])
                  ->orWhereBetween('received_date', [$startOfMonth, $endOfMonth])
                  ->orWhereBetween('created_at', [$startOfMonth, $endOfMonth]);
            })->count() : 0;
        $invoicesMonthUsd = $schema->hasTable('invoices')
            ? (float) $db->table('invoices')->where('supplier_id', $supId)->where(function ($q) use ($startOfMonth, $endOfMonth) {
                $q->whereBetween('created_invoice_date', [$startOfMonth, $endOfMonth])
                  ->orWhereBetween('received_date', [$startOfMonth, $endOfMonth])
                  ->orWhereBetween('created_at', [$startOfMonth, $endOfMonth]);
            })->sum('total_usd') : 0.0;
        $lastInvoiceDate = $schema->hasTable('invoices')
            ? ($db->table('invoices')->where('supplier_id', $supId)->max('created_invoice_date') ?: $db->table('invoices')->where('supplier_id', $supId)->max('created_at')) : null;

        $statusKey = 'inactive';
        $statusLabel = 'Inactivo';
        $statusColor = 'secondary';

        if ($invoicesMonth > 0 || $ordersMonth > 0) {
            $statusKey = 'buying_now';
            $statusLabel = 'Comprando este mes';
            $statusColor = 'success';
        } elseif ($invoicesTotal > 0 || $ordersTotal > 0) {
            $statusKey = 'historical_buyer';
            $statusLabel = 'Comprador histórico';
            $statusColor = 'info';
        } elseif ($syncsTotal > 0 || $currentProducts > 0) {
            $statusKey = 'catalog_only';
            $statusLabel = 'Solo consulta catálogo';
            $statusColor = 'warning';
        }

        return [
            'tenant_id' => $tenantId,
            'tenant_name' => ucfirst($tenantId),
            'tenant_domain' => $tenantDomain,
            'tenant_url' => "https://{$tenantDomain}",
            'supplier_found' => true,
            'supplier_name' => $supName,
            'supplier_id' => $supId,
            'is_active' => $isActive,
            'catalog_updates_month' => $syncsMonth,
            'catalog_updates_total' => $syncsTotal,
            'last_sync_date' => $lastSync ? Carbon::parse($lastSync)->format('Y-m-d H:i') : null,
            'current_products' => $currentProducts,
            'orders_month' => $ordersMonth,
            'orders_total' => $ordersTotal,
            'orders_month_usd' => 0.0,
            'orders_total_usd' => round($ordersTotalUsd, 2),
            'last_order_date' => $lastOrderDate ? Carbon::parse($lastOrderDate)->format('Y-m-d') : null,
            'invoices_month' => $invoicesMonth,
            'invoices_total' => $invoicesTotal,
            'invoices_month_usd' => round($invoicesMonthUsd, 2),
            'invoices_total_usd' => round($invoicesTotalUsd, 2),
            'last_invoice_date' => $lastInvoiceDate ? Carbon::parse($lastInvoiceDate)->format('Y-m-d') : null,
            'relationship_status' => $statusKey,
            'relationship_label' => $statusLabel,
            'relationship_color' => $statusColor,
        ];
    }

    /**
     * Genera una fila por defecto para un tenant sin proveedor configurado
     */
    private function createEmptyRow(string $tenantId, string $tenantDomain, string $reason): array
    {
        return [
            'tenant_id' => $tenantId,
            'tenant_name' => ucfirst($tenantId),
            'tenant_domain' => $tenantDomain,
            'tenant_url' => "https://{$tenantDomain}",
            'supplier_found' => false,
            'supplier_name' => $reason,
            'supplier_id' => null,
            'is_active' => false,
            'catalog_updates_month' => 0,
            'catalog_updates_total' => 0,
            'last_sync_date' => null,
            'current_products' => 0,
            'orders_month' => 0,
            'orders_total' => 0,
            'orders_month_usd' => 0.0,
            'orders_total_usd' => 0.0,
            'last_order_date' => null,
            'invoices_month' => 0,
            'invoices_total' => 0,
            'invoices_month_usd' => 0.0,
            'invoices_total_usd' => 0.0,
            'last_invoice_date' => null,
            'relationship_status' => 'not_registered',
            'relationship_label' => 'Sin registrar',
            'relationship_color' => 'secondary',
        ];
    }
}
