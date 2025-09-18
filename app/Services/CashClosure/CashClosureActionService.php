<?php

namespace App\Services\CashClosure;

use App\Models\CashClosing;
use Illuminate\Support\Facades\Auth;
use Exception;
use Illuminate\Support\Collection;
use App\Http\Requests\CashClosure\CloseCashClosureRequest;
use App\Models\Order;

class CashClosureActionService
{

    public function allCashClosing(): ?CashClosing
    {
        $sellerId = Auth::id();
        $cashClosing = CashClosing::where('seller_id',$sellerId)->where('status', CashClosing::OPEN)->first();
        if (!$cashClosing) {
            throw new Exception('No se encontró un cierre de caja abierto.');
        }
        return $cashClosing;
    }
    public function closeCashClosing(CloseCashClosureRequest $request): Collection 
    {
        $validatedData = $request->validated();

        $cashClosure = CashClosing::findOrFail($validatedData['id']);
        $pendingOrders = $cashClosure->orders()
                                     ->whereIn('status', [Order::RESERVED, Order::PENDING])
                                     ->get();

        if ($pendingOrders->isNotEmpty()) {
            return collect([
                'success' => false,
                'message' => 'No se puede cerrar la caja. Hay órdenes pendientes o reservadas.',
                'data' => [
                    'pending_orders_count' => $pendingOrders->count(),
                    'pending_order_ids' => $pendingOrders->pluck('id')->toArray(),
                ],
            ]);
        }

        $cashClosure->update([
        //     'status' => CashClosing::CLOSED,
        //     'total_cop' => $validatedData['total_cop'],
        //     'cop_spare' => $validatedData['sobrante_en_peso'],
        //     'cop_delivered' => $validatedData['entregar_efectivo_cop'],
         ]);

        return collect([
            'success' => true,
            'message' => 'Caja cerrada exitosamente.',
            // 'cash_closure' => $cashClosure,
            'data' => $validatedData,
        ]);

    }
}
