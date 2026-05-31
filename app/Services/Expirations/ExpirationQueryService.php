<?php

namespace App\Services\Expirations;

use App\Http\Resources\ExpirationResource;
use App\Models\ProductLot;
use App\Models\InventoryMovement;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ExpirationQueryService
{
    /**
     * Obtiene la consulta de lotes próximos a vencer filtrada.
     */
    public function getExpiringLotsQuery(Request $request)
    {
        $query = ProductLot::with(['product.laboratory', 'product.origin', 'product.category'])
            ->whereHas('product')
            ->where('quantity', '>', 0);

        // Filtro por búsqueda de texto
        if ($request->has('q')) {
            $search = $request->input('q');
            $isStrict = $request->boolean('isStrict');

            $query->whereHas('product', function ($q) use ($search, $isStrict) {
                if ($isStrict) {
                    $q->where('name', 'like', "%{$search}%")
                        ->orWhere('barcode', 'like', "%{$search}%");
                } else {
                    $q->where('name', 'like', "%{$search}%")
                        ->orWhere('active_ingredient', 'like', "%{$search}%")
                        ->orWhere('barcode', 'like', "%{$search}%");
                }
            });
        }

        // Filtro por laboratorio
        if ($request->has('laboratory_id')) {
            $query->whereHas('product', function ($q) use ($request) {
                $q->where('laboratory_id', $request->input('laboratory_id'));
            });
        }

        // Filtro por rango de fechas
        $startDate = $request->input('startDate') ?? $request->input('start_date');
        $endDate = $request->input('endDate') ?? $request->input('end_date');

        if ($startDate || $endDate) {
            if ($startDate && $endDate) {
                $query->whereBetween('expiration_date', [$startDate, $endDate]);
            } elseif ($startDate) {
                $query->where('expiration_date', '>=', $startDate);
            } else {
                $query->where('expiration_date', '<=', $endDate);
            }
        } else {
            // Por defecto: próximos 6 meses
            $query->whereBetween('expiration_date', [
                now()->startOfDay()->toDateString(),
                now()->addMonths(6)->endOfMonth()->toDateString(),
            ]);
        }

        return $query->orderBy('expiration_date', 'asc');
    }

    /**
     * Obtiene los lotes próximos a vencer con paginación y recursos.
     */
    public function getExpiringLotsPaginated(Request $request)
    {
        $perPage = $request->input('per_page', 15);
        $lots = $this->getExpiringLotsQuery($request)->paginate($perPage);

        return ExpirationResource::collection($lots);
    }

    /**
     * Obtiene todos los lotes próximos a vencer (sin paginación).
     */
    public function getExpiringLotsAll(Request $request)
    {
        $lots = $this->getExpiringLotsQuery($request)->get();
        return ExpirationResource::collection($lots);
    }

    /**
     * Obtiene un resumen histórico de lotes caducados agrupados por mes.
     */
    public function getExpiredLotsSummary()
    {
        $summaries = DB::table('expired_logs')
            ->select([
                DB::raw("DATE_FORMAT(created_at, '%Y-%m') as month"),
                DB::raw('COUNT(*) as total_products'),
                DB::raw('SUM(total_lost_value) as total_cost')
            ])
            ->groupBy(DB::raw("DATE_FORMAT(created_at, '%Y-%m')"))
            ->orderBy('month', 'desc')
            ->get();

        foreach ($summaries as $summary) {
            $summary->donation_count = DB::table('donative_logs')
                ->join('expired_logs', 'donative_logs.expired_log_id', '=', 'expired_logs.id')
                ->whereRaw("DATE_FORMAT(expired_logs.created_at, '%Y-%m') = ?", [$summary->month])
                ->distinct('donative_logs.donation_id')
                ->count('donative_logs.donation_id');

            // El usuario solicita poder usar el reajuste las veces que desee, por lo que nunca se considera inhabilitado
            $summary->has_price_adjustment = false;
        }

        return $summaries;
    }

    /**
     * Obtiene todos los registros de lotes caducados de un mes específico para propósitos de reporte.
     */
    public function getExpiredLotsForMonth(string $month)
    {
        $logs = \App\Models\ExpiredLog::with(['product.laboratory'])
            ->whereRaw("DATE_FORMAT(created_at, '%Y-%m') = ?", [$month])
            ->get();

        return [
            'month' => $month,
            'logs' => $logs,
            'total_cost' => $logs->sum('total_lost_value'),
            'total_items' => $logs->count(),
            'total_quantity' => $logs->sum('expired_quantity'),
        ];
    }

    public function getSoldExpiringLotsThisMonth()
    {
        $startOfMonth = Carbon::now()->startOfMonth()->toDateTimeString();
        $endOfMonth = Carbon::now()->endOfMonth()->toDateTimeString();

        return InventoryMovement::with([
            'product' => function ($q) {
                $q->withTrashed()->with('laboratory');
            },
            'productLot',
            'user.employee'
        ])
        ->where('movement_type', 'sale')
        ->whereBetween('movement_date', [$startOfMonth, $endOfMonth])
        ->whereExists(function ($query) use ($startOfMonth, $endOfMonth) {
            $query->select(DB::raw(1))
                ->from('product_lots')
                ->where(function ($sub) {
                    $sub->whereColumn('product_lots.id', 'inventory_movements.product_lot_id')
                        ->orWhere(function ($orSub) {
                            $orSub->whereNull('inventory_movements.product_lot_id')
                                  ->whereColumn('product_lots.product_id', 'inventory_movements.product_id');
                        });
                })
                ->whereBetween('product_lots.expiration_date', [$startOfMonth, $endOfMonth]);
        })
        ->orderBy('movement_date', 'desc')
        ->take(4)
        ->get();
    }
}
