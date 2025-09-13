<?php

namespace App\Services\CashClosure;

use Illuminate\Http\Request;
use Illuminate\Database\Eloquent\Builder;
use App\Models\CashClosing;
use function Amp\Dns\query;
use Illuminate\Support\Facades\Auth;

class CashClosureQueryService
{

     private function getBaseQuery(): Builder
    {
         $sellerId = Auth::id();
        return CashClosing::query()->where('seller_id',$sellerId)->where('status', CashClosing::CLOSED);
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

}
