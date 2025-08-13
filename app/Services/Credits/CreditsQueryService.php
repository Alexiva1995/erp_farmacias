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
            return $query->orderBy('id', 'asc');
        }

        switch ($sortBy) {
            case 'id':
                return $query->orderBy("id", $orderBy);
            case 'pending_amount':
                return $query->orderBy("pending_amount", $orderBy);
        }

        return $query;
    }

  public function getFilteredQuery(Request $request): Builder
    {
        $query = $this->getBaseQuery();

        $query = $this->applySorting($query, $request->input('sortBy'), $request->input('orderBy', 'asc'));

       // $this->applySorting($query, $request->input('sortBy'), $request->input('orderBy', 'asc'));
        return $query;
    }

}
