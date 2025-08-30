<?php

namespace App\Services\Returns;

use App\Services\Resources\ResourceService;
use App\Models\Client;
use App\Models\Order;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Exception;
use Illuminate\Database\Eloquent\Builder;

class ReturnsActionService
{
     public function __construct(private ResourceService $resourceService)
    {
    }
    

    public function searchOrdersReturns(string $identification, array $options): Builder
    {
        try {
        $client = Client::where('identification', $identification)->first();
        if (!$client) {
            throw new Exception('No se encontró el cliente.');
        }


        $query = Order::where('client_id', $client->id)
            ->where('created_at', '>=', Carbon::now()->subHours(48))
            ->with('client');

        if (isset($options['sortBy']) && !empty($options['sortBy'])) {
            $sortBy = $options['sortBy'];
            $orderBy = $options['orderBy'] ?? 'asc';
            $query->orderBy($sortBy, $orderBy);
        }
        
        return $query;
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }
}
