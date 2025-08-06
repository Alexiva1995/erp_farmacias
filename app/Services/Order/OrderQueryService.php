<?php

namespace App\Services\Order;

use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class OrderQueryService
{

      private function getBaseQuery($valor): Builder
    {
        if($valor=='Completed'){
            $start = now()->startOfDay();
            $end = now()->endOfDay();
            return Order::query()->where('status',$valor)->whereBetween('created_at', [$start, $end])->with('client','seller');
        }else if ($valor=='all'){
            return Order::query()->with('client','seller');
        }
        
        return Order::query()->where('status',$valor)->with('client','seller');
    }


private function applyFilters(Builder $query, array $filters): Builder
{
    // FILTRO POR ID (si se proporciona)
    if (!empty($filters['id'])) {
        $query->where('id', $filters['id']);
    }

    // FILTRO GENERAL (si se proporciona)
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

    // Filtro de moneda, se mantiene igual
    if (!empty($filters['currency'])) {
        $query->where('currency', $filters['currency']);
    }

    return $query;
}

      public function getFilteredQuery(Request $request, $valor): Builder
    {
        $query = $this->getBaseQuery($valor);
          $filters = [
            'id' => $request->id,
            'q' => $request->q,
            'currency' => $request->currency
        ];
        $this->applyFilters($query, $filters);
        return $query;
    }
}
