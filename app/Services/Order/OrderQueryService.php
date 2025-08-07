<?php

namespace App\Services\Order;

use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class OrderQueryService
{

      private function getBaseQuery($valor): Builder
    {
        if($valor=='Completed'){
            $start = now()->startOfDay();
            $end = now()->endOfDay();
            return Order::query()->where('status',$valor)->whereBetween('order_date', [$start, $end])->with('client','seller');
        }else if ($valor=='all'){
            return Order::query()->with('client','seller');
        }
        
        return Order::query()->where('status',$valor)->with('client','seller');
    }


private function applyFilters(Builder $query, array $filters): Builder
{
    if (!empty($filters['id'])) {
        $query->where('id', $filters['id']);
    }

    if (!empty($filters['q'])) {
        $searchTerm = "%{$filters['q']}%";
        
        $query->where(function ($subQuery) use ($searchTerm) {
            // Búsqueda en la relación 'client' por identificación
            $subQuery->whereHas('client', function ($clientQuery) use ($searchTerm) {
                $clientQuery->where('identification', 'like', $searchTerm);
            });

            // Búsqueda en la relación 'seller' por username
            $subQuery->orWhereHas('seller', function ($sellerQuery) use ($searchTerm) {
                $sellerQuery->where('username', 'like', $searchTerm);
            });
        });
    }

    if (!empty($filters['currency'])) {
        $query->where('currency', $filters['currency']);
    }

    if (!empty($filters['state'])) {
        $query->where('status', $filters['state']);
    }

    if (!empty($filters['start_date']) || !empty($filters['end_date'])) {
         $startDate = !empty($filters['start_date']) ? Carbon::parse($filters['start_date'])->startOfDay() : null;
        $endDate = !empty($filters['end_date']) ? Carbon::parse($filters['end_date'])->endOfDay() : null;

          if ($startDate && $endDate) {
                $query->whereBetween('order_date', [$startDate, $endDate]);
            } elseif ($startDate) {
                $query->where('order_date', '>=', $startDate);
            } elseif ($endDate) {
                $query->where('order_date', '<=', $endDate);
            }
        }

    return $query;
}

      public function getFilteredQuery(Request $request, $valor): Builder
    {
        $query = $this->getBaseQuery($valor);
          $filters = [
            'id' => $request->id,
            'q' => $request->q,
            'currency' => $request->currency,
            'start_date' => $request->start_date,
            'end_date' => $request->end_date,
            'state' => $request->state,
        ];
        $this->applyFilters($query, $filters);
        return $query;
    }
}
