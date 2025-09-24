<?php

namespace App\Services\CashClosure;

use Illuminate\Http\Request;
use Illuminate\Database\Eloquent\Builder;
use App\Models\CashClosing;
use function Amp\Dns\query;
use Illuminate\Support\Facades\Auth;
use Illuminate\Database\Eloquent\Relations\Relation; 
use App\Models\Order;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class CashClosureQueryService
{

     private function getBaseQuery(): Builder
    {
         $sellerId = Auth::id();
        // $sellerId = 2;
        return CashClosing::query()->where('seller_id',$sellerId)->where('status', CashClosing::CLOSED)->with('orders.details.product');
    }


     private function applySorting(Builder $query, ?string $sortBy, string $orderBy): Builder
    {
          if (empty($sortBy)) {
            return $query->orderBy('id', 'desc');
        }

        switch ($sortBy) {
            case 'closing_date':
                return $query->orderBy('closing_date', $orderBy); 
        }

        return $query;
    }

      public function getFilteredQuery(Request $request): Builder
    {

         $query = $this->getBaseQuery();

          $query = $this->applySorting(
            $query, 
            $request->input('sortBy'), 
            $request->input('orderBy', 'desc')
        );

        return $query;
    }


      private function getBaseQueryOrder(): ?CashClosing
    {
        $sellerId = Auth::id();
        // $sellerId = 2;
        return CashClosing::where('seller_id', $sellerId)
                          ->where('status', CashClosing::OPEN)
                          ->first();
    }


     private function applyOrderSorting(Relation $query, ?string $sortBy, string $orderBy): Relation
    {
        if (empty($sortBy)) {
            return $query->orderBy('id', 'desc');
        }
        switch ($sortBy) {
            case 'created_at':
                return $query->orderBy('created_at', $orderBy);
            case 'total_amount':
                return $query->orderBy('total_amount', $orderBy);
            default:
                return $query->orderBy($sortBy, $orderBy);
        }
    }

      public function getFilteredQueryOrder(Request $request): Relation
    {

        $cashClosing = $this->getBaseQueryOrder();
        if (is_null($cashClosing)) {
            throw new ModelNotFoundException('No hay un cierre de caja abierto para este vendedor.');
        }
        $ordersQuery = $cashClosing->orders()->with('client');
        $ordersQuery->where('status', Order::COMPLETED); 
        $ordersQuery = $this->applyOrderSorting(
            $ordersQuery,
            $request->input('sortBy'),
            $request->input('orderBy', 'desc')
        );
        return $ordersQuery;
    }

}
