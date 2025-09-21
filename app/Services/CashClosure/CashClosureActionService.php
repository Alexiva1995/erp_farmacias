<?php

namespace App\Services\CashClosure;

use App\Models\CashClosing;
use Illuminate\Support\Facades\Auth;
use Exception;
use Illuminate\Support\Collection;
use App\Http\Requests\CashClosure\CloseCashClosureRequest;
use App\Models\Order;
use Illuminate\Support\Carbon;
use Illuminate\Http\JsonResponse;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Mail;
use App\Mail\ReporteCierreCajaMail;

class CashClosureActionService
{

    public function allCashClosing(): ?CashClosing
    {
        $sellerId = Auth::id();
        $cashClosing = CashClosing::where('seller_id',$sellerId)->where('status', CashClosing::OPEN)->with('orders')->first();
        if (!$cashClosing) {
            throw new Exception('No se encontró un cierre de caja abierto.');
        }
        return $cashClosing;
    }
   public function closeCashClosing(CloseCashClosureRequest $request): JsonResponse
{
    $validatedData = $request->validated();
    $sellerId = Auth::id();
    $cashClosure = CashClosing::findOrFail($validatedData['id']);
    $pendingOrders = $cashClosure->orders()->whereIn('status', [Order::RESERVED, Order::PENDING])->get();

    if ($pendingOrders->isNotEmpty()) {
        return response()->json([
            'success' => false,
            'message' => 'No se puede cerrar la caja. Hay órdenes pendientes o reservadas.',
            'data' => [
                'pending_orders_count' => $pendingOrders->count(),
                'pending_order_ids' => $pendingOrders->pluck('id')->toArray(),
            ],
        ], 409);
    }

    $cashClosure->update([
        'status' => CashClosing::CLOSED,
        'total_cop' => $validatedData['total_cop'],
        'cop_spare' => $validatedData['sobrante_en_peso'],
        'cop_delivered' => $validatedData['entregar_efectivo_cop'],
    ]);

    $cashClosure->refresh()->load('orders');
    $htmlContent = mb_convert_encoding($validatedData['ticket_html'], 'UTF-8', 'UTF-8');
    $pdf = PDF::loadHTML($htmlContent);
    $pdfContent = $pdf->output();
    $destinatariosTo = ['cierres@farmaciabs.com'];
    $namePDF = 'Cierre de caja' . $cashClosure->id . '.pdf';
    Mail::to($destinatariosTo)->send(new ReporteCierreCajaMail($pdfContent, $namePDF));
    CashClosing::create([
        'seller_id' => $sellerId,
        'status' => CashClosing::OPEN,
        'closing_date' => Carbon::now(),
    ]);


    return response()->json([
        'success' => true,
        'message' => 'Caja cerrada exitosamente.',
        'cash_closure_data' => $cashClosure,
    ], 200);
}
}
