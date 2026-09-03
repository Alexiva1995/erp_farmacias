<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Contracts\Repositories\SupplierReturnsRepositoryInterface;
use App\Models\ProductLot;
use Illuminate\Support\Facades\DB;

class SupplierReturnsRepository implements SupplierReturnsRepositoryInterface
{
    /**
     * Obtiene lotes con stock positivo que vencen dentro de los próximos $days días.
     * Incluye datos de producto, laboratorio, proveedor y fecha de ingreso del lote.
     */
    public function getLotsExpiringSoon(array $filters, int $days = 90): array
    {
        $query = ProductLot::query()
            ->join('products', 'product_lots.product_id', '=', 'products.id')
            ->leftJoin('laboratories', 'products.laboratory_id', '=', 'laboratories.id')
            ->leftJoin('groups_laboratories', 'laboratories.group_id', '=', 'groups_laboratories.id')
            ->leftJoin('suppliers', 'product_lots.supplier_id', '=', 'suppliers.id')
            ->select(
                'products.id as product_id',
                'products.name as product_name',
                'products.barcode',
                'products.active_ingredient',
                'products.presentation',
                'laboratories.id as laboratory_id',
                'laboratories.name as laboratory_name',
                'groups_laboratories.id as group_id',
                'groups_laboratories.name as group_name',
                'suppliers.id as supplier_id',
                'suppliers.name as supplier_name',
                'product_lots.id as lot_id',
                'product_lots.lot_number',
                'product_lots.expiration_date',
                'product_lots.quantity',
                'product_lots.unit_cost',
                'product_lots.amount_usd',
                // Fecha de ingreso del lote como proxy de fecha de compra
                'product_lots.created_at as purchase_date',
                // Monto total del lote = unidades × costo unitario
                DB::raw('ROUND(product_lots.quantity * product_lots.unit_cost, 2) as total_amount'),
                // Días restantes para el vencimiento
                DB::raw('DATEDIFF(product_lots.expiration_date, CURDATE()) as days_to_expiry')
            )
            ->where('product_lots.quantity', '>', 0)
            ->where('product_lots.expiration_date', '>=', now()->toDateString())
            ->where('product_lots.expiration_date', '<=', now()->addDays($days)->toDateString())
            ->whereNull('products.deleted_at')
            ->where('products.is_deleted', false)
            ->orderBy('laboratories.name')
            ->orderBy('product_lots.expiration_date');

        // Filtro por laboratorio específico
        if (!empty($filters['laboratory_id'])) {
            $query->where('products.laboratory_id', $filters['laboratory_id']);
        }

        // Filtro por proveedor
        if (!empty($filters['supplier_id'])) {
            $query->where('product_lots.supplier_id', $filters['supplier_id']);
        }

        // Búsqueda por nombre o código de barra
        if (!empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('products.name', 'like', "%{$search}%")
                  ->orWhere('products.barcode', 'like', "%{$search}%")
                  ->orWhere('product_lots.lot_number', 'like', "%{$search}%");
            });
        }

        return $query->get()->toArray();
    }
}
