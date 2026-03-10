<?php

namespace App\Repository;

use App\Models\ProductSupplier;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;

class MarketOpportunityRepository
{
    /**
     * Construye la consulta base para las oportunidades de mercado.
     * Compara product_suppliers.unit_cost_usd con el MIN(unit_cost) de product_lots.
     */
    public function builderMarketOpportunities($filtros): Builder
    {
        $doceMesesAtras = now()->subMonths(12)->toDateString();

        // Subconsulta para el costo mínimo histórico por producto en los lotes (últimos 12 meses)
        $minHistoricCostSubquery = DB::table('product_lots')
            ->select('product_id', DB::raw('MIN(unit_cost) as min_historic_cost'))
            ->whereDate('created_at', '>=', $doceMesesAtras)
            ->groupBy('product_id');

        $query = ProductSupplier::query()
            ->select(
                'product_suppliers.id',
                'product_suppliers.product_id',
                'product_suppliers.supplier_id',
                'product_suppliers.unit_cost_usd',
                'product_suppliers.name as product_name_supplier',
                'products.name as product_name_inventory',
                'products.active_ingredient as active_ingredient_inventory',
                'products.unit_cost as inventory_unit_cost',
                'laboratories.name as laboratory_name',
                DB::raw('LEAST(COALESCE(historic.min_historic_cost, products.unit_cost), products.unit_cost) as effective_min_cost'),
                DB::raw('(LEAST(COALESCE(historic.min_historic_cost, products.unit_cost), products.unit_cost) - product_suppliers.unit_cost_usd) as saving_amount'),
                DB::raw('ROUND(((LEAST(COALESCE(historic.min_historic_cost, products.unit_cost), products.unit_cost) - product_suppliers.unit_cost_usd) / LEAST(COALESCE(historic.min_historic_cost, products.unit_cost), products.unit_cost)) * 100, 2) as saving_percentage')
            )
            ->join('products', 'product_suppliers.product_id', '=', 'products.id')
            ->leftJoin('laboratories', 'products.laboratory_id', '=', 'laboratories.id')
            ->leftJoinSub($minHistoricCostSubquery, 'historic', function ($join) {
                $join->on('products.id', '=', 'historic.product_id');
            })
            // Solo registros con costo mayor a 0 para evitar divisiones por cero y datos basura
            ->where('product_suppliers.unit_cost_usd', '>', 0)
            // Solo productos donde el precio actual del proveedor es MENOR al costo efectivo mínimo (lote o inventario)
            ->whereRaw('product_suppliers.unit_cost_usd < LEAST(COALESCE(historic.min_historic_cost, products.unit_cost), products.unit_cost)');

        // Aplicar filtros similares a los de productos
        if (!empty($filtros['q'])) {
            $query->where(function ($q) use ($filtros) {
                $searchTerm = '%' . $filtros['q'] . '%';
                $q->where('product_suppliers.name', 'like', $searchTerm)
                  ->orWhere('product_suppliers.barcode_match', 'like', $searchTerm)
                  ->orWhere('products.name', 'like', $searchTerm);
            });
        }

        if (!empty($filtros['laboratoryId'])) {
            $labIds = is_array($filtros['laboratoryId']) ? $filtros['laboratoryId'] : [$filtros['laboratoryId']];
            $query->whereIn('products.laboratory_id', $labIds);
        }

        if (!empty($filtros['productId'])) {
            $productIds = is_array($filtros['productId']) ? $filtros['productId'] : [$filtros['productId']];
            $query->whereIn('products.id', $productIds);
        }

        // Ordenamiento por defecto: mayor ahorro porcentual primero
        $sortBy = $filtros['sortBy'] ?? 'saving_percentage';
        $orderBy = $filtros['orderBy'] ?? 'desc';

        // Mapeo de columnas para ordenamiento si es necesario (ej: price -> unit_cost_usd)
        if ($sortBy === 'price') $sortBy = 'unit_cost_usd';

        return $query->orderBy($sortBy, $orderBy);
    }

    public function getPaginatedOpportunities($filtros, $perPage = 10): LengthAwarePaginator
    {
        return $this->builderMarketOpportunities($filtros)->paginate($perPage);
    }

    public function getAllOpportunities($filtros)
    {
        return $this->builderMarketOpportunities($filtros)->get();
    }
}
