<?php
namespace App\Services\Order;

use App\Models\Order;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use App\Models\CashClosing;
use Exception;

class OrderActionService
{
    public function createOrder(array $data): Order
    {
        DB::beginTransaction();
        try {
            
             $openCashRegisterClosing = CashClosing::where('seller_id', $data['seller_id'])
                                                          ->where('status', CashClosing::OPEN) // <-- Asume que tienes una constante OPEN
                                                          ->first();
            if (!$openCashRegisterClosing){
                throw new Exception('No se encontró un cierre de caja abierto para el vendedor.');
            } else {
                $data['cash_closing_id'] = $openCashRegisterClosing->id;
                $data['total_amount'] = $data['total_amount'] ?? 0;
                $data['money_returns'] = $data['money_returns'] ?? 0;
                $data['payment_methods'] = null;
            }

            $order = Order::create($data);

            DB::commit();
            return $order;
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error al crear la orden: ' . $e->getMessage() . ' en línea ' . $e->getLine() . ' en archivo ' . $e->getFile());
            throw $e;
        }
    }
}
