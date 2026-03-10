<?php

namespace App\Repositories;

use App\Contracts\Repositories\MarketOpportunityRepositoryInterface;
use App\Models\ProductSupplier;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

/**
 * Clase MarketOpportunityRepository
 * 
 * Se encarga de la lógica de base de datos para identificar oportunidades de mercado
 * basadas en la comparación de precios de proveedores contra costos históricos y actuales.
 */
class MarketOpportunityRepository implements MarketOpportunityRepositoryInterface
{
    /**
     * Construye la consulta base para las oportunidades de mercado.
     * 
     * Compara product_suppliers.unit_cost_usd con el menor valor entre
     * el costo histórico de lotes (12 meses) y el costo actual de inventario.
     *
     * @param array $filtros
     * @return Builder
     */
    protected function builderMarketOpportunities(array $filtros): Builder
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
                // El costo de referencia es el menor entre el precio pagado históricamente y el costo actual en inventario
                DB::raw('LEAST(COALESCE(historic.min_historic_cost, products.unit_cost), products.unit_cost) as effective_min_cost'),
                // Monto de ahorro: Diferencia entre el costo de referencia y el precio del proveedor
                DB::raw('(LEAST(COALESCE(historic.min_historic_cost, products.unit_cost), products.unit_cost) - product_suppliers.unit_cost_usd) as saving_amount'),
                // Porcentaje de ahorro
                DB::raw('ROUND(((LEAST(COALESCE(historic.min_historic_cost, products.unit_cost), products.unit_cost) - product_suppliers.unit_cost_usd) / LEAST(COALESCE(historic.min_historic_cost, products.unit_cost), products.unit_cost)) * 100, 2) as saving_percentage')
            )
            ->join('products', 'product_suppliers.product_id', '=', 'products.id')
            ->leftJoin('laboratories', 'products.laboratory_id', '=', 'laboratories.id')
            ->leftJoinSub($minHistoricCostSubquery, 'historic', function ($join) {
                $join->on('products.id', '=', 'historic.product_id');
            })
            // Filtrar registros con costo mayor a 0 para evitar errores matemáticos
            ->where('product_suppliers.unit_cost_usd', '>', 0)
            // Solo productos donde el proveedor es más económico que nuestra mejor referencia de costo
            ->whereRaw('product_suppliers.unit_cost_usd < LEAST(COALESCE(historic.min_historic_cost, products.unit_cost), products.unit_cost)');

        // Aplicación de filtros de búsqueda
        if (!empty($filtros['q'])) {
            $query->where(function ($q) use ($filtros) {
                $searchTerm = '%' . $filtros['q'] . '%';
                $q->where('product_suppliers.name', 'like', $searchTerm)
                  ->orWhere('product_suppliers.barcode_match', 'like', $searchTerm)
                  ->orWhere('products.name', 'like', $searchTerm);
            });
        }

        // Filtro por laboratorio
        if (!empty($filtros['laboratoryId'])) {
            $labIds = is_array($filtros['laboratoryId']) ? $filtros['laboratoryId'] : [$filtros['laboratoryId']];
            $query->whereIn('products.laboratory_id', $labIds);
        }

        // Filtro por producto específico
        if (!empty($filtros['productId'])) {
            $productIds = is_array($filtros['productId']) ? $filtros['productId'] : [$filtros['productId']];
            $query->whereIn('products.id', $productIds);
        }

        // Ordenamiento (Por defecto mayor porcentaje de ahorro)
        $sortBy = $filtros['sortBy'] ?? 'saving_percentage';
        $orderBy = $filtros['orderBy'] ?? 'desc';

        if ($sortBy === 'price') {
            $sortBy = 'unit_cost_usd';
        }

        return $query->orderBy($sortBy, $orderBy);
    }

    /**
     * Obtener oportunidades de mercado paginadas.
     *
     * @param array $filtros
     * @param int $perPage
     * @return LengthAwarePaginator
     */
    public function getPaginatedOpportunities(array $filtros, int $perPage = 10): LengthAwarePaginator
    {
        return $this->builderMarketOpportunities($filtros)->paginate($perPage);
    }

    /**
     * Obtener todas las oportunidades de mercado sin paginar.
     *
     * @param array $filtros
     * @return Collection
     */
    public function getAllOpportunities(array $filtros): Collection
    {
        return $this->builderMarketOpportunities($filtros)->get();
    }
}
