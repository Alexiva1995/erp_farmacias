<?php

namespace App\Services\Returns;

use App\Services\Resources\ResourceService;
use App\Models\Client;
use App\Models\Order;
use App\Models\ProductLot;
use App\Models\ReturnEntry;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Exception;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

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
            ->with('client','details.product');

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

    public function productReturn(Request $request)
    {
         DB::beginTransaction();

        try {
            $orderData = $request->order;
            $productData = $request->product;
            $orderDetail = collect($orderData['details'])->firstWhere('product_id', $productData['id']);

            if (!$orderDetail) {
                throw new Exception('No se encontró el detalle del producto en la orden.');
            }
            $orderDetail = (object) $orderDetail;
         
            $returnAmount = $orderDetail->quantity * $orderDetail->price;
            $client = $orderDetail->client;
            
            if (!$client) {
                throw new Exception('No se encontró el cliente asociado a la orden.');
            }
            //$client->balance += $returnAmount;
            //$client->save();


              $lot = ProductLot::where('product_id', $productData->id)
                      ->where('expiration_date', '>', now())
                      ->where('quantity', '>', 0)
                      ->orderByDesc('expiration_date')
                      ->first();

            if ($lot) {
                $lot->quantity += $orderDetail->quantity;
                $lot->save();
            }else{
                 throw new Exception('No se encontró lote vigente para ese producto.');
            }

            
            ReturnEntry::create([
                'order_id' => $orderData->id,
                'product_id' => $productData->id,
                'quantity' => $orderDetail->quantity,
                'amount_refunded' => $returnAmount,
                'return_date' => $returnAmount,
                'status' => 'Created',
            ]);

            DB::commit();
            return [
                'success' => true,
                'message' => 'Devolución procesada con éxito.',
            ];
        } catch (Exception $e) {
            DB::rollBack();
            throw new Exception($e->getMessage());
        }
    }
}
