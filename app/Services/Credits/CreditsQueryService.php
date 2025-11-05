<?php

namespace App\Services\Credits;

use Illuminate\Http\Request;
use Illuminate\Database\Eloquent\Builder;
use App\Models\Credit;

use function Amp\Dns\query;

class CreditsQueryService
{


     private function getBaseQuery(): Builder
    {
        return Credit::query()->with('client');
    }

    private function applySorting(Builder $query, ?string $sortBy, string $orderBy): Builder
    {
          if (empty($sortBy)) {
            return $query->orderBy('total_pending_amount', 'desc');
        }

        switch ($sortBy) {
            case 'client_full_name':
                return $query->leftJoin('clients', 'clients.id', '=', 'credits.client_id')
                             ->orderBy('clients.name', $orderBy);
            case 'total_pending_amount':
                return $query->orderBy('total_pending_amount', $orderBy);
        }

        return $query;
    }

  public function getFilteredQuery(Request $request): Builder
    {

         $query = $this->getBaseQuery()
            ->select('client_id')
            ->selectRaw('SUM(pending_amount) as total_pending_amount')
            ->selectRaw('GROUP_CONCAT(id) as credit_ids')
            ->selectRaw('COUNT(CASE WHEN status != "Paid" THEN 1 END) = 0 as is_paid') 
            ->groupBy('client_id');

          $query = $this->applySorting(
            $query, 
            $request->input('sortBy'), 
            $request->input('orderBy', 'desc')
        );

        return $query;
    }

}
