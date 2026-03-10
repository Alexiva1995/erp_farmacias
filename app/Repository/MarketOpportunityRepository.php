<?php

namespace App\Repository;

use App\Models\SuppliersConfigProduct;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;

class MarketOpportunityRepository
{
    /**
     * Construye la consulta base para las oportunidades de mercado.
     * Compara suppliers_config_products.price con el MIN(unit_cost) de invoice_details de los últimos 12 meses.
     */
    public function builderMarketOpportunities($filtros): Builder
    {
        $doceMesesAtras = now()->subMonths(12)->toDateString();

        // Subconsulta para el costo mínimo histórico por producto en los últimos 12 meses
        $minHistoricCostSubquery = DB::table('invoice_details')
            ->join('invoices', 'invoice_details.invoice_id', '=', 'invoices.id')
            ->select('product_id', DB::raw('MIN(unit_cost) as min_historic_cost'))
            ->whereDate('invoices.received_date', '>=', $doceMesesAtras)
            ->groupBy('product_id');

        $query = SuppliersConfigProduct::query()
            ->select(
                'suppliers_config_products.*',
                'products.name as product_name_inventory',
                'products.active_ingredient as active_ingredient_inventory',
                'laboratories.name as laboratory_name',
                'historic.min_historic_cost',
                DB::raw('(historic.min_historic_cost - suppliers_config_products.price) as saving_amount'),
                DB::raw('ROUND(((historic.min_historic_cost - suppliers_config_products.price) / historic.min_historic_cost) * 100, 2) as saving_percentage')
            )
            ->join('products', 'suppliers_config_products.barcode', '=', 'products.barcode')
            ->leftJoin('laboratories', 'products.laboratory_id', '=', 'laboratories.id')
            ->joinSub($minHistoricCostSubquery, 'historic', function ($join) {
                $join->on('products.id', '=', 'historic.product_id');
            })
            // Solo productos donde el precio actual del proveedor es MENOR al mínimo histórico
            ->whereRaw('suppliers_config_products.price < historic.min_historic_cost');

        // Aplicar filtros similares a los de productos
        if (!empty($filtros['q'])) {
            $query->where(function ($q) use ($filtros) {
                $searchTerm = '%' . $filtros['q'] . '%';
                $q->where('suppliers_config_products.product_name', 'like', $searchTerm)
                  ->orWhere('suppliers_config_products.barcode', 'like', $searchTerm)
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
