<?php

namespace App\Http\Resources\Financial;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class FinancialStatementDetailResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'transactions' => $this->resource['transactions'],
            'pagination'   => $this->resource['pagination'],
            'page_totals'  => $this->resource['page_totals'] ?? null,
        ];
    }
}
