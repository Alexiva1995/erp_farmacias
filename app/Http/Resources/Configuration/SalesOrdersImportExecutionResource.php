<?php

declare(strict_types=1);

namespace App\Http\Resources\Configuration;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SalesOrdersImportExecutionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'orders_created'        => (int) ($this['orders_created'] ?? 0),
            'order_details_created' => (int) ($this['order_details_created'] ?? 0),
            'clients_auto_created'  => (int) ($this['clients_auto_created'] ?? 0),
            'orders_skipped'        => (int) ($this['orders_skipped'] ?? 0),
            'total_amount_bs'       => (float) ($this['total_amount_bs'] ?? 0.0),
            'total_amount_usd'      => (float) ($this['total_amount_usd'] ?? 0.0),
        ];
    }
}
