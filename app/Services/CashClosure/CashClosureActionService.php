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
use App\Mail\ReporteHistoryCierreCajaMail;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use App\Models\DailyCashClosure;

class CashClosureActionService
{

    public function allCashClosing(): ?CashClosing
    {
        $sellerId = Auth::id();
        //$sellerId = 2;
        $cashClosing = CashClosing::where('seller_id', $sellerId)->where('status', CashClosing::OPEN)->with('orders.details.product')->first();
        if (!$cashClosing) {
            throw new Exception('No se encontró un cierre de caja abierto.');
        }
        return $cashClosing;
    }
    public function closeCashClosing(CloseCashClosureRequest $request): JsonResponse
    {
        $validatedData = $request->validated();
        $sellerId = Auth::id();
        //$sellerId = 2;
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
        //pdf cierre
        $htmlContent = mb_convert_encoding($validatedData['ticket_html'], 'UTF-8', 'UTF-8');
        $pdf = PDF::loadHTML($htmlContent);
        $pdfContent = $pdf->output();
        $destinatariosTo = ['cierres@farmaciabs.com'];
        $namePDF = 'Cierre de caja' . $cashClosure->id . '.pdf';
        Mail::to($destinatariosTo)->send(new ReporteCierreCajaMail($pdfContent, $namePDF));
        //pdf history
        $historyHtmlContent = mb_convert_encoding($validatedData['history_html'], 'UTF-8', 'UTF-8');
        $pdfHistory = Pdf::loadHTML($historyHtmlContent);
        $pdfHistoryContent = $pdfHistory->output();
        $destinatariosToHistory = ['alexisvalera@farmaciabs.com'];
        $nameHistoryPDF = 'Historial_de_Cierre_' . $cashClosure->id . '.pdf';
        Mail::to($destinatariosToHistory)->send(new ReporteHistoryCierreCajaMail($pdfHistoryContent, $nameHistoryPDF));

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


    public function closeDailyCashClosure(User $seller)
    {
        $cashClosings = CashClosing::where('seller_id', $seller->id)
            ->whereDate('closing_date', Carbon::today())
            ->get();

        if ($cashClosings->isEmpty()) {
            return;
        }
        DB::beginTransaction();
        try {

            $dailyClosure = DailyCashClosure::create([
                'total_sales'  => $cashClosings->sum('total_sales'),
                'total_usd'     => $cashClosings->sum('total_usd') + $cashClosings->sum('usd_credit'),
                'total_cop'     => $cashClosings->sum('total_cop'),
                'total_bs'      => $cashClosings->sum('total_bs'),
                'bs_card'       => $cashClosings->sum('bs_card'),
                'bs_mobile'     => $cashClosings->sum('bs_mobile'),
                'usd_delivered' => $cashClosings->sum('usd_delivered'),
                'cop_delivered' => $cashClosings->sum('cop_delivered'),
                'bs_delivered'  => $cashClosings->sum('bs_delivered'),
            ]);

            foreach ($cashClosings as $cashClosing) {

                if ($cashClosing->status === CashClosing::OPEN) {
                    $cashClosing->update([
                        'status' => CashClosing::CLOSED,
                        'daily_closure_id' => $dailyClosure->id,
                    ]);
                } else if (empty($cashClosing->daily_closure_id)) {
                    $cashClosing->update(['daily_closure_id' => $dailyClosure->id]);
                }
            }

            CashClosing::create([
                'seller_id' => $seller->id,
                'status' => CashClosing::OPEN,
                'closing_date' => Carbon::now(),
            ]);

            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            throw new \Exception('Error al realizar el cierre de caja diario: ' . $e->getMessage());
        }
    }

    public function getMonthlySalesSummaryData(): array {
    
        $currentMonthStart = Carbon::now()->startOfMonth();
        $currentMonthEnd = Carbon::now();
        $lastMonthStart = Carbon::now()->subMonthNoOverflow()->startOfMonth();
        $lastMonthEnd = Carbon::now()->subMonthNoOverflow()->endOfMonth();

        $currentDays = $currentMonthStart->diffInDays($currentMonthEnd) + 1;
        $currentDays = ($currentDays === 0) ? 1 : $currentDays;

         $currentMonthTotal = CashClosing::where('status', CashClosing::CLOSED)
            ->whereBetween('closing_date', [$currentMonthStart, $currentMonthEnd])
            ->sum('total_sales');

        $currentAverage = $currentMonthTotal / $currentDays;

        $lastDays = $lastMonthStart->diffInDays($lastMonthEnd) + 1;
        $lastDays = ($lastDays === 0) ? 1 : $lastDays;

        $lastMonthTotal = CashClosing::where('status', CashClosing::CLOSED)
            ->whereBetween('closing_date', [$lastMonthStart, $lastMonthEnd])
            ->sum('total_sales');

        $lastAverage = $lastMonthTotal / $lastDays;

        $percentageChange = 0;
        $isPositive = true;
        
        if ($lastAverage > 0) {
            $percentageChange = (($currentAverage - $lastAverage) / $lastAverage) * 100;
            $isPositive = $percentageChange >= 0;
        }

         return [
            'current_month_average' => number_format($currentAverage, 2, '.', ','),
            'last_month_average' => number_format($lastAverage, 2, '.', ','),
            'percentage_change' => number_format(abs($percentageChange), 1),
            'is_positive' => $isPositive,
        ];
    }
}
