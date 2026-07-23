<?php

declare(strict_types=1);

namespace App\Repositories;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class DiscountReportRepository
{
    /**
     * Obtiene los KPIs de impacto financiero
     */
    public function getKPIs(array $filters): array
    {
        $startDate = $filters['start_date'] ?? now()->startOfMonth()->format('Y-m-d');
        $endDate = $filters['end_date'] ?? now()->format('Y-m-d');

        $query = DB::table('order_details')
            ->join('orders', 'order_details.order_id', '=', 'orders.id')
            ->leftJoin('product_pack_items', function ($join) {
                $join->on('order_details.pack_id', '=', 'product_pack_items.pack_id')
                     ->on('order_details.product_id', '=', 'product_pack_items.product_id');
            })
            ->where('orders.status', 'Completed')
            ->whereBetween('orders.created_at', [$startDate . ' 00:00:00', $endDate . ' 23:59:59']);

        // Dinero Cedido ($) en USD reales
        $moneyGiven = (clone $query)->sum(DB::raw("CASE 
            WHEN order_details.pack_id IS NOT NULL THEN
                CASE 
                    WHEN product_pack_items.discount_percentage > 0 
                         AND product_pack_items.discount_percentage < 100 
                    THEN order_details.quantity * order_details.unit_price_usd * (CAST(product_pack_items.discount_percentage AS REAL) / (100.0 - product_pack_items.discount_percentage))
                    ELSE 0 
                END
            ELSE
                CASE 
                    WHEN order_details.price > 0 AND order_details.price_before_discount > order_details.price 
                    THEN order_details.quantity * order_details.unit_price_usd * ((order_details.price_before_discount - order_details.price) / order_details.price)
                    ELSE 0 
                END
        END"));

        // Venta Total con Descuento ($) en USD reales (solo de los productos descontados o en pack)
        $totalSalesWithDiscount = (clone $query)
            ->where(function ($sq) {
                $sq->where('order_details.discount_percentage', '>', 0)
                   ->orWhereNotNull('order_details.pack_id');
            })
            ->sum(DB::raw('order_details.quantity * order_details.unit_price_usd'));

        // Penetración de Ofertas (%)
        $totalOrders = DB::table('orders')
            ->where('status', 'Completed')
            ->whereBetween('created_at', [$startDate . ' 00:00:00', $endDate . ' 23:59:59'])
            ->count();

        $discountedOrders = DB::table('orders')
            ->where('status', 'Completed')
            ->whereBetween('created_at', [$startDate . ' 00:00:00', $endDate . ' 23:59:59'])
            ->whereExists(function ($q) {
                $q->select(DB::raw(1))
                    ->from('order_details')
                    ->whereColumn('order_details.order_id', 'orders.id')
                    ->where(function ($sq) {
                        $sq->where('discount_percentage', '>', 0)
                           ->orWhereNotNull('pack_id');
                    });
            })
            ->count();

        $penetration = $totalOrders > 0 ? ($discountedOrders / $totalOrders) * 100 : 0;

        // Descuento Promedio Global (%) ponderado e inteligente incluyendo packs
        $avgDiscount = (clone $query)
            ->where(function ($q) {
                $q->where('order_details.discount_percentage', '>', 0)
                  ->orWhereNotNull('order_details.pack_id');
            })
            ->avg(DB::raw("CASE 
                WHEN order_details.pack_id IS NOT NULL 
                THEN product_pack_items.discount_percentage
                ELSE order_details.discount_percentage 
            END")) ?? 0;

        return [
            'total_money_given' => (float)$moneyGiven,
            'total_sales_with_discount' => (float)$totalSalesWithDiscount,
            'offer_penetration' => (float)$penetration,
            'avg_global_discount' => (float)$avgDiscount,
        ];
    }

    /**
     * Obtiene la distribución por tipo de promoción
     */
    public function getDistributionByType(array $filters): Collection
    {
        $startDate = $filters['start_date'] ?? now()->startOfMonth()->format('Y-m-d');
        $endDate = $filters['end_date'] ?? now()->format('Y-m-d');

        return DB::table('order_details')
            ->join('orders', 'order_details.order_id', '=', 'orders.id')
            ->leftJoin('product_pack_items', function ($join) {
                $join->on('order_details.pack_id', '=', 'product_pack_items.pack_id')
                     ->on('order_details.product_id', '=', 'product_pack_items.product_id');
            })
            ->select(
                DB::raw("CASE 
                    WHEN order_details.pack_id IS NOT NULL THEN 'Pack'
                    WHEN order_details.discount_type = 'individual' THEN 'Individual'
                    WHEN order_details.discount_type = 'category' THEN 'Categoría'
                    WHEN order_details.discount_type = 'company' THEN 'Empresa'
                    WHEN order_details.discount_type = 'doctor' THEN 'Médico'
                    WHEN order_details.discount_type IN ('prescription', 'recipe') THEN 'Récipe'
                    WHEN order_details.discount_type = 'expiration' THEN 'Caducidad'
                    ELSE 'Otro'
                END as promo_type"),
                DB::raw("SUM(CASE 
                    WHEN order_details.pack_id IS NOT NULL THEN
                        CASE 
                            WHEN product_pack_items.discount_percentage > 0 
                                 AND product_pack_items.discount_percentage < 100 
                            THEN order_details.quantity * order_details.unit_price_usd * (CAST(product_pack_items.discount_percentage AS REAL) / (100.0 - product_pack_items.discount_percentage))
                            ELSE 0 
                        END
                    ELSE
                        CASE 
                            WHEN order_details.price > 0 AND order_details.price_before_discount > order_details.price 
                            THEN order_details.quantity * order_details.unit_price_usd * ((order_details.price_before_discount - order_details.price) / order_details.price)
                            ELSE 0 
                        END
                END) as money_given"),
                DB::raw('COUNT(DISTINCT order_details.order_id) as transaction_count')
            )
            ->where('orders.status', 'Completed')
            ->whereBetween('orders.created_at', [$startDate . ' 00:00:00', $endDate . ' 23:59:59'])
            ->where(function ($q) {
                $q->where('discount_percentage', '>', 0)
                  ->orWhereNotNull('pack_id');
            })
            ->groupBy('promo_type')
            ->get();
    }

    /**
     * Obtiene métricas específicas de rendimiento
     */
    public function getPerformanceHighlights(array $filters): array
    {
        $startDate = $filters['start_date'] ?? now()->startOfMonth()->format('Y-m-d');
        $endDate = $filters['end_date'] ?? now()->format('Y-m-d');

        // Rendimiento de Packs vs Individuales
        $packUnits = DB::table('order_details')
            ->join('orders', 'order_details.order_id', '=', 'orders.id')
            ->where('orders.status', 'Completed')
            ->whereBetween('orders.created_at', [$startDate . ' 00:00:00', $endDate . ' 23:59:59'])
            ->whereNotNull('pack_id')
            ->sum('quantity');

        $individualUnits = DB::table('order_details')
            ->join('orders', 'order_details.order_id', '=', 'orders.id')
            ->where('orders.status', 'Completed')
            ->whereBetween('orders.created_at', [$startDate . ' 00:00:00', $endDate . ' 23:59:59'])
            ->where('discount_type', 'individual')
            ->sum('quantity');

        // Conversión Médicos y Récipes
        $totalRevenue = DB::table('orders')
            ->where('status', 'Completed')
            ->whereBetween('created_at', [$startDate . ' 00:00:00', $endDate . ' 23:59:59'])
            ->sum('total_amount_usd');

        $medicalRevenue = DB::table('orders')
            ->where('status', 'Completed')
            ->whereBetween('created_at', [$startDate . ' 00:00:00', $endDate . ' 23:59:59'])
            ->whereExists(function ($q) {
                $q->select(DB::raw(1))
                    ->from('order_details')
                    ->whereColumn('order_details.order_id', 'orders.id')
                    ->whereIn('discount_type', ['doctor', 'prescription', 'recipe']);
            })
            ->sum('total_amount_usd');

        $medicalConversion = $totalRevenue > 0 ? ($medicalRevenue / $totalRevenue) * 100 : 0;

        // Recuperación por Caducidad en USD reales
        $expiryRecovery = DB::table('order_details')
            ->join('orders', 'order_details.order_id', '=', 'orders.id')
            ->where('orders.status', 'Completed')
            ->whereBetween('orders.created_at', [$startDate . ' 00:00:00', $endDate . ' 23:59:59'])
            ->where('discount_type', 'expiration')
            ->sum(DB::raw('order_details.quantity * order_details.unit_price_usd'));

        return [
            'pack_vs_individual' => [
                ['name' => 'Packs', 'value' => (int)$packUnits],
                ['name' => 'Individuales', 'value' => (int)$individualUnits],
            ],
            'medical_recipe_conversion' => (float)$medicalConversion,
            'expiry_recovery_amount' => (float)$expiryRecovery,
        ];
    }

    /**
     * Obtiene el ranking de mejores y peores ofertas
     */
    public function getOfferRankings(array $filters): array
    {
        $startDate = $filters['start_date'] ?? now()->startOfMonth()->format('Y-m-d');
        $endDate = $filters['end_date'] ?? now()->format('Y-m-d');

        $baseQuery = DB::table('order_details')
            ->join('orders', 'order_details.order_id', '=', 'orders.id')
            ->join('products', 'order_details.product_id', '=', 'products.id')
            ->select(
                'products.name as product_name',
                DB::raw("CASE 
                    WHEN order_details.pack_id IS NOT NULL THEN 'Pack'
                    ELSE order_details.discount_type
                END as type"),
                DB::raw('SUM(order_details.quantity) as units_sold'),
                DB::raw('SUM(order_details.quantity * order_details.unit_price_usd) as revenue'),
                DB::raw('SUM(order_details.quantity * (order_details.unit_price_usd - order_details.unit_cost)) as total_margin')
            )
            ->where('orders.status', 'Completed')
            ->whereBetween('orders.created_at', [$startDate . ' 00:00:00', $endDate . ' 23:59:59'])
            ->where(function ($q) {
                $q->where('discount_percentage', '>', 0)
                  ->orWhereNotNull('pack_id');
            })
            ->groupBy('product_name', 'type');

        $topOffers = (clone $baseQuery)
            ->orderByDesc('revenue')
            ->limit(10)
            ->get();

        $bottomOffers = (clone $baseQuery)
            ->orderBy('revenue', 'asc')
            ->limit(10)
            ->get();

        return [
            'top_offers' => $topOffers,
            'bottom_offers' => $bottomOffers,
        ];
    }

    /**
     * Obtiene los datos para la auditoría de descuentos
     */
    public function getAuditData(array $filters)
    {
        $startDate = $filters['start_date'] ?? now()->startOfMonth()->format('Y-m-d');
        $endDate = $filters['end_date'] ?? now()->format('Y-m-d');
        $perPage = $filters['itemsPerPage'] ?? 10;

        $query = DB::table('order_details')
            ->join('orders', 'order_details.order_id', '=', 'orders.id')
            ->join('products', 'order_details.product_id', '=', 'products.id')
            ->join('users as sellers', 'orders.seller_id', '=', 'sellers.id')
            ->leftJoin('product_pack_items', function ($join) {
                $join->on('order_details.pack_id', '=', 'product_pack_items.pack_id')
                     ->on('order_details.product_id', '=', 'product_pack_items.product_id');
            })
            ->select([
                'orders.id as ticket_id',
                'orders.created_at as date',
                'products.name as product_name',
                DB::raw("CASE 
                    WHEN order_details.pack_id IS NOT NULL THEN 'Pack'
                    WHEN order_details.discount_type = 'individual' THEN 'Individual'
                    WHEN order_details.discount_type = 'category' THEN 'Categoría'
                    WHEN order_details.discount_type = 'company' THEN 'Empresa'
                    WHEN order_details.discount_type = 'doctor' THEN 'Médico'
                    WHEN order_details.discount_type IN ('prescription', 'recipe') THEN 'Récipe'
                    WHEN order_details.discount_type = 'expiration' THEN 'Caducidad'
                    ELSE 'Otro'
                END as discount_type"),
                DB::raw("CASE 
                    WHEN order_details.pack_id IS NOT NULL 
                    THEN product_pack_items.discount_percentage
                    ELSE order_details.discount_percentage 
                END as discount_percentage"),
                DB::raw("CASE 
                    WHEN order_details.pack_id IS NOT NULL THEN
                        CASE 
                            WHEN product_pack_items.discount_percentage > 0 
                                 AND product_pack_items.discount_percentage < 100 
                            THEN order_details.quantity * order_details.unit_price_usd * (CAST(product_pack_items.discount_percentage AS REAL) / (100.0 - product_pack_items.discount_percentage))
                            ELSE 0 
                        END
                    ELSE
                        CASE 
                            WHEN order_details.price > 0 AND order_details.price_before_discount > order_details.price 
                            THEN order_details.quantity * order_details.unit_price_usd * ((order_details.price_before_discount - order_details.price) / order_details.price)
                            ELSE 0 
                        END
                END as discount_amount"),
                'sellers.username as seller_name'
            ])
            ->where('orders.status', 'Completed')
            ->whereBetween('orders.created_at', [$startDate . ' 00:00:00', $endDate . ' 23:59:59']);

        // Aplicar filtro por tipo de descuento
        if (!empty($filters['discount_type']) && $filters['discount_type'] !== 'Todos') {
            $type = $filters['discount_type'];
            if ($type === 'Pack') {
                $query->whereNotNull('order_details.pack_id');
            } else {
                $query->whereNull('order_details.pack_id');
                
                $mapTypes = [
                    'Individual' => ['individual'],
                    'Categoría' => ['category'],
                    'Empresa' => ['company'],
                    'Médico' => ['doctor'],
                    'Récipe' => ['prescription', 'recipe'],
                    'Caducidad' => ['expiration']
                ];
                
                if (isset($mapTypes[$type])) {
                    $query->whereIn('order_details.discount_type', $mapTypes[$type]);
                } else {
                    $query->whereRaw('0 = 1'); // Si envían algo inválido no retorna filas
                }
            }
        } else {
            // Consulta base de registros con algún descuento o pack
            $query->where(function ($q) {
                $q->where('discount_percentage', '>', 0)
                  ->orWhereNotNull('pack_id');
            });
        }

        return $query->orderByDesc('orders.created_at')
            ->paginate($perPage);
    }
}
