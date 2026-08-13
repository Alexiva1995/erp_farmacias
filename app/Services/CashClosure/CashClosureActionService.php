<?php

declare(strict_types=1);

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
use App\Services\Resources\ResourceService;
use App\Models\Transaction;
use App\Models\Category;

class CashClosureActionService
{

    protected ResourceService $resourceService;

    public function __construct(ResourceService $resourceService)
    {
        $this->resourceService = $resourceService;
    }


    public function generateClosingTransactions(CashClosing $cashClosure)
    {
        $metrics = [
            'bs_mobile' => ['currency' => 'BS', 'type' => 'MOBILE'],
            'bs_transfer' => ['currency' => 'BS', 'type' => 'TRANSFER'],
            'bs_card_debito' => ['currency' => 'BS', 'type' => 'CARD'],
            'bs_card_credit' => ['currency' => 'BS', 'type' => 'CARD'],
            'bs_cash' => ['currency' => 'BS', 'type' => 'CASH'],
            'cop_delivered' => ['currency' => 'COP', 'type' => 'CASH'],
            'cop_transfer' => ['currency' => 'COP', 'type' => 'TRANSFER'],
            'cop_spare' => ['currency' => 'COP', 'type' => 'CASH'],
            'usd_delivered' => ['currency' => 'USD', 'type' => 'CASH'],
            'usd_binance' => ['currency' => 'USD', 'type' => 'BINANCE'],
            'usd_paypal' => ['currency' => 'USD', 'type' => 'PAYPAL'],
            'usd_credit' => ['currency' => 'USD', 'type' => 'CREDIT'],

            // Pagos de créditos
            'bs_cash_payment_credit' => ['currency' => 'BS', 'type' => 'CASH'],
            'bs_card_payment_credit' => ['currency' => 'BS', 'type' => 'CARD'],
            'bs_transfer_payment_credit' => ['currency' => 'BS', 'type' => 'TRANSFER'],
            'bs_mobile_payment_credit' => ['currency' => 'BS', 'type' => 'MOBILE'],
            'cop_cash_payment_credit' => ['currency' => 'COP', 'type' => 'CASH'],
            'cop_transfer_payment_credit' => ['currency' => 'COP', 'type' => 'TRANSFER'],
            'usd_transfer_payment_credit' => ['currency' => 'USD', 'type' => 'TRANSFER'],
            'usd_cash_payment_credit' => ['currency' => 'USD', 'type' => 'CASH'],
            'usd_paypal_payment_credit' => ['currency' => 'USD', 'type' => 'PAYPAL'],
            'usd_binance_payment_credit' => ['currency' => 'USD', 'type' => 'BINANCE'],
        ];

        // Obtenemos los valores numéricos de las tasas (rate) indexados por el código de moneda
        $rates = DB::table('exchange_rates')->pluck('rate', 'currency_code');

        foreach ($metrics as $field => $info) {
            $amount = $cashClosure->$field;

            if ($amount > 0) {
                // Lógica de asignación de tasa
                $exchangeRateValue = 1.0000; // Valor base para USD

                if ($info['currency'] === 'BS') {
                    // Para BS usamos la tasa EUR (bolívares por euro)
                    $exchangeRateValue = $rates['EUR'] ?? 1.0000;
                } elseif ($info['currency'] !== 'USD') {
                    $exchangeRateValue = $rates[$info['currency']] ?? 1.0000;
                }

                Transaction::create([
                    'user_id' => $cashClosure->seller_id,
                    'category_id' => null,
                    'description' => "Cierre de caja #" . $cashClosure->id,
                    'currency' => $info['currency'],
                    'type' => $info['type'],
                    'amount' => $amount,
                    'movement_type' => 'IN',
                    'transaction_date' => Carbon::now(),
                    'exchange_rate' => $exchangeRateValue, // Nuevo campo decimal
                ]);
            }
        }
    }

    public function allCashClosing(): ?CashClosing
    {
        $sellerId = Auth::id();
        $cashClosing = CashClosing::where('seller_id', $sellerId)
            ->where('status', CashClosing::OPEN)
            ->orderByDesc('id')
            ->first();

        if ($cashClosing) {
            $cashClosing->setAttribute('blind_cash_closure', !empty(\App\Models\GeneralSetting::first()?->blind_cash_closure));
        }

        return $cashClosing;
    }
    public function closeCashClosing(CloseCashClosureRequest $request): JsonResponse
    {
        $validatedData = $request->validated();
        $sellerId = Auth::id();
        $cashClosure = CashClosing::findOrFail($validatedData['id']);
        // Si hay órdenes pendientes o reservadas asociadas al cierre, las pasamos a ABANDONED para permitir el cierre
        $pendingOrders = $cashClosure->orders()->whereIn('status', [Order::RESERVED, Order::PENDING])->get();
        if ($pendingOrders->isNotEmpty()) {
            foreach ($pendingOrders as $pendingOrder) {
                $pendingOrder->status = Order::ABANDONED;
                $pendingOrder->save();
            }
        }

        $updateData = [
            'status' => CashClosing::CLOSED,
            'total_cop' => $validatedData['total_cop'],
            'cop_spare' => $validatedData['sobrante_en_peso'],
            'cop_delivered' => $validatedData['entregar_efectivo_cop'],
            'closing_date' => Carbon::now(),
        ];

        if (!empty($validatedData['is_blind'])) {
            $decCop = (float) ($validatedData['declared_cop'] ?? 0);
            $decCopTransfer = (float) ($validatedData['declared_cop_transfer'] ?? 0);
            $decUsd = (float) ($validatedData['declared_usd'] ?? 0);
            $decCredit = (float) ($validatedData['declared_credit'] ?? 0);
            $decBsMobile = (float) ($validatedData['declared_bs_mobile'] ?? 0);
            $decBsCard = (float) ($validatedData['declared_bs_card'] ?? 0);

            $sysCop = (float) $cashClosure->cop_delivered;
            $sysCopTransfer = (float) $cashClosure->cop_transfer;
            $sysUsd = (float) $cashClosure->usd_delivered;
            $sysCredit = (float) $cashClosure->usd_credit;
            $sysBsMobile = (float) ($cashClosure->bs_transfer + $cashClosure->bs_mobile);
            $sysBsCard = (float) ($cashClosure->bs_card_debito + $cashClosure->bs_card_credit);

            $sobrante = max(0, $decCop - $sysCop);
            $updateData['cop_spare'] = $sobrante;
            $updateData['cop_delivered'] = $sysCop + $sobrante;
            // total_cop original
            $origTotalCop = (float) $cashClosure->total_cop;
            $updateData['total_cop'] = $origTotalCop + $sobrante;

            $updateData['declared_cop'] = $decCop;
            $updateData['declared_cop_transfer'] = $decCopTransfer;
            $updateData['declared_usd'] = $decUsd;
            $updateData['declared_credit'] = $decCredit;
            $updateData['declared_bs_mobile'] = $decBsMobile;
            $updateData['declared_bs_card'] = $decBsCard;

            $updateData['diff_cop'] = round($decCop - $sysCop, 2);
            $updateData['diff_cop_transfer'] = round($decCopTransfer - $sysCopTransfer, 2);
            $updateData['diff_usd'] = round($decUsd - $sysUsd, 2);
            $updateData['diff_credit'] = round($decCredit - $sysCredit, 2);
            $updateData['diff_bs_mobile'] = round($decBsMobile - $sysBsMobile, 2);
            $updateData['diff_bs_card'] = round($decBsCard - $sysBsCard, 2);

            $mismatches = [];
            $notes = [];

            if (round($decCop, 2) != round($sysCop, 2)) {
                $mismatches[] = 'cop';
                $notes[] = "COP Físico: Declarado " . number_format($decCop, 2) . " / Sistema " . number_format($sysCop, 2);
            }
            if (round($decCopTransfer, 2) != round($sysCopTransfer, 2)) {
                $mismatches[] = 'cop_transfer';
                $notes[] = "Transf. COP (Bancolombia): Declarado " . number_format($decCopTransfer, 2) . " / Sistema " . number_format($sysCopTransfer, 2);
            }
            if (round($decUsd, 2) != round($sysUsd, 2)) {
                $mismatches[] = 'usd';
                $notes[] = "USD: Declarado " . number_format($decUsd, 2) . " / Sistema " . number_format($sysUsd, 2);
            }
            if (round($decCredit, 2) != round($sysCredit, 2)) {
                $mismatches[] = 'credit';
                $notes[] = "Crédito USD: Declarado " . number_format($decCredit, 2) . " / Sistema " . number_format($sysCredit, 2);
            }
            if (round($decBsMobile, 2) != round($sysBsMobile, 2)) {
                $mismatches[] = 'bs_mobile';
                $notes[] = "Transf/PM BS: Declarado " . number_format($decBsMobile, 2) . " / Sistema " . number_format($sysBsMobile, 2);
            }
            if (round($decBsCard, 2) != round($sysBsCard, 2)) {
                $mismatches[] = 'bs_card';
                $notes[] = "Tarjetas BS: Declarado " . number_format($decBsCard, 2) . " / Sistema " . number_format($sysBsCard, 2);
            }

            $updateData['blind_mismatches'] = json_encode($mismatches);
            $updateData['blind_note'] = implode(' | ', $notes);

            // Enviar notificación detallada por Telegram al administrador sobre la declaración del cierre ciego si la notificación está activa
            try {
                $isNotifyActive = \App\Models\TelegramCommand::where('module', 'generales')
                    ->where('command', '/cierre_individual')
                    ->value('is_active') ?? true;

                if ($isNotifyActive) {
                    $telegram = resolve(\App\Services\TelegramService::class);
                    $sellerName = $cashClosure->seller?->username ?? "Cajero #{$cashClosure->seller_id}";
                    $hasMismatch = !empty($mismatches);
                    
                    $msg = $hasMismatch 
                        ? "🚨 *[ALERTA: DESCUADRE EN CIERRE DE CAJA]* 🚨\n\n" 
                        : "✅ *[NOTIFICACIÓN: CIERRE DE CAJA RECIBIDO]* ✅\n\n";
                        
                    $msg .= "👤 *Cajero:* {$sellerName}\n"
                          . "🆔 *Cierre ID:* #{$cashClosure->id}\n"
                          . "📅 *Fecha:* " . now()->format('d/m/Y g:i A') . "\n\n";

                    if ($hasMismatch) {
                        $msg .= "*Descuadres / Diferencias encontradas:*\n";
                        foreach ($notes as $note) {
                            $msg .= "• {$note}\n";
                        }
                    } else {
                        $msg .= "✨ *Estado:* Cierre cuadrado correctamente sin diferencias.\n";
                    }

                    $msg .= "\n📊 *Ventas registradas por el Sistema:*\n"
                          . "• Efectivo COP: " . number_format($sysCop, 2) . " COP\n"
                          . "• Efectivo USD: $" . number_format($sysUsd, 2) . "\n"
                          . "• Pago Móvil / Transf BS: Bs " . number_format($sysBsMobile, 2) . "\n"
                          . "• Tarjetas BS: Bs " . number_format($sysBsCard, 2) . "\n"
                          . "• Crédito USD: $" . number_format($sysCredit, 2) . "\n"
                          . "• Transferencia COP: " . number_format($sysCopTransfer, 2) . " COP\n";

                    $msg .= "\n💵 *Declaración física del cajero:*\n"
                          . "• Efectivo COP: " . number_format($decCop, 2) . " COP\n"
                          . "• Efectivo USD: $" . number_format($decUsd, 2) . "\n"
                          . "• Pago Móvil / Transf BS: Bs " . number_format($decBsMobile, 2) . "\n"
                          . "• Tarjetas BS: Bs " . number_format($decBsCard, 2) . "\n";

                    $telegram->sendToAdmin($msg);
                }
            } catch (\Exception $e) {
                \Illuminate\Support\Facades\Log::error('[TelegramNotify] Error al enviar alerta de cierre a Telegram: ' . $e->getMessage());
            }
        }

        $cashClosure->update($updateData);

        $cashClosure->refresh()->load(['orders', 'seller.employee']);

        //pdf cierre
        /*  $htmlContent = mb_convert_encoding($validatedData['ticket_html'], 'UTF-8', 'UTF-8');
          $pdf = PDF::loadHTML($htmlContent);
          $pdfContent = $pdf->output();
          $destinatariosTo = ['cierres@farmaciabs.com'];
          $namePDF = 'Cierre de caja' . $cashClosure->id . '.pdf';*/
        //Mail::to($destinatariosTo)->send(new ReporteCierreCajaMail($pdfContent, $namePDF));

        //pdf history
        /* $historyHtmlContent = mb_convert_encoding($validatedData['history_html'], 'UTF-8', 'UTF-8');
         $pdfHistory = Pdf::loadHTML($historyHtmlContent);
         $pdfHistoryContent = $pdfHistory->output();
         $destinatariosToHistory = ['alexisvalera@farmaciabs.com'];
         $nameHistoryPDF = 'Historial_de_Cierre_' . $cashClosure->id . '.pdf';*/
        //Mail::to($destinatariosToHistory)->send(new ReporteHistoryCierreCajaMail($pdfHistoryContent, $nameHistoryPDF));

        // $this->generateClosingTransactions($cashClosure);




        return response()->json([
            'success' => true,
            'message' => 'Caja cerrada exitosamente.',
            'cash_closure_data' => $cashClosure,
        ], 200);
    }


    public function closeDailyCashClosure()
    {
        $cashClosings = CashClosing::whereDate('closing_date', Carbon::today())
            ->where('total_sales', '>', 0.0)
            ->whereNull('daily_closure_id')
            ->get();

        if ($cashClosings->isEmpty()) {
            return;
        }

        DB::beginTransaction();
        try {
            $dailyCashClosureInstance = new \App\Models\DailyCashClosure();
            $TotalCopPaymentInUsd = $dailyCashClosureInstance->getTotalCopPaymentInUsd($cashClosings);
            $TotalBsPaymentInUsd = $dailyCashClosureInstance->getTotalBsPaymentInUsd($cashClosings);
            $TotalCopDeliveryInUsd = $dailyCashClosureInstance->getTotalCopDeliveryInUsd($cashClosings);
            $TotalBsDeliveryInUsd = $dailyCashClosureInstance->getTotalBsDeliveryInUsd($cashClosings);

            $dailyClosure = DailyCashClosure::create([
                'total_sales'          => $cashClosings->sum('total_sales'),
                'total_usd'            => $cashClosings->sum('total_usd'),
                'total_cop'            => $cashClosings->sum('total_cop'),
                'total_bs'             => $cashClosings->sum('total_bs'),
                // Suma colapsada (compatibilidad)
                'bs_card'              => $cashClosings->sum('bs_card_debito') + $cashClosings->sum('bs_card_credit'),
                // Desglose Bs por método
                'bs_cash'              => $cashClosings->sum('bs_cash'),
                'bs_card_debito'       => $cashClosings->sum('bs_card_debito'),
                'bs_card_credit'       => $cashClosings->sum('bs_card_credit'),
                'bs_transfer'          => $cashClosings->sum('bs_transfer'),
                'bs_mobile'            => $cashClosings->sum('bs_mobile'),
                // Desglose USD por método
                'usd_cash'             => $cashClosings->sum('usd_cash'),
                'usd_transfer'         => $cashClosings->sum('usd_transfer'),
                'usd_paypal'           => $cashClosings->sum('usd_paypal'),
                'usd_binance'          => $cashClosings->sum('usd_binance'),
                // Desglose COP por método
                'cop_cash'             => $cashClosings->sum('cop_cash'),
                'cop_transfer'         => $cashClosings->sum('cop_transfer'),
                'cop_spare'            => $cashClosings->sum('cop_spare'),
                // Totales consolidados
                'usd_delivered'        => $cashClosings->sum('usd_delivered'),
                'cop_delivered'        => $cashClosings->sum('cop_delivered'),
                'bs_delivered'         => $cashClosings->sum('bs_delivered'),
                'total_credits'        => $cashClosings->sum('usd_credit'),
                'total_payment_credit' => $cashClosings->sum('usd_transfer_payment_credit') + $cashClosings->sum('usd_cash_payment_credit') + $cashClosings->sum('usd_paypal_payment_credit') + $cashClosings->sum('usd_binance_payment_credit') + $TotalCopPaymentInUsd + $TotalBsPaymentInUsd,
                'total_delivery'       => $TotalCopDeliveryInUsd + $cashClosings->sum('usd_delivered') + $cashClosings->sum('usd_transfer') + $cashClosings->sum('usd_paypal') + $cashClosings->sum('usd_binance') + $TotalBsDeliveryInUsd,
            ]);

            foreach ($cashClosings as $cashClosing) {
                $cashClosing->update([
                    'status' => CashClosing::CLOSED,
                    'daily_closure_id' => $dailyClosure->id,
                    'closing_date' => $cashClosing->created_at ?? Carbon::now(),
                ]);

                $this->generateClosingTransactions($cashClosing);
            }



            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            throw new \Exception('Error al realizar el cierre de caja diario consolidado: ' . $e->getMessage());
        }

        /* $cashClosings = CashClosing::where('seller_id', $seller->id)
             ->whereDate('closing_date', Carbon::today())
             ->where('total_sales', '>', 0.0)
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
         }*/
    }

    public function getMonthlySalesSummaryData(): array
    {

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


    private function getFormattedRates(): array
    {
        $rates = $this->resourceService->getAllExchangeRate();
        $formattedRates = [];
        foreach ($rates as $rate) {
            $formattedRates[$rate->currency_code] = (float) $rate->rate;
        }
        return $formattedRates;
    }

    public function getCashClosingsForMonthlySummary(array $dailyClosureIds): array
    {
        if (empty($dailyClosureIds)) {
            return ['summary' => collect(), 'global_total_sales' => 0.0];
        }

        // Subconsulta para obtener los totales históricos de las órdenes agrupadas por cierre de caja
        // Esto captura el valor real en USD que tenía cada moneda en el momento de la venta
        $ordersSubquery = DB::table('orders')
            ->where('status', Order::COMPLETED)
            ->select('cash_closing_id',
                DB::raw('SUM(CASE WHEN currency = "BS" THEN total_amount_usd ELSE 0 END) as total_bs_usd_hist'),
                DB::raw('SUM(CASE WHEN currency = "COP" THEN total_amount_usd ELSE 0 END) as total_cop_usd_hist'),
                DB::raw('SUM(CASE WHEN currency = "USD" THEN total_amount_usd ELSE 0 END) as total_usd_usd_hist')
            )
            ->groupBy('cash_closing_id');

        $sellerSummary = CashClosing::query()
            ->whereIn('daily_closure_id', $dailyClosureIds)
            ->join('users', 'users.id', '=', 'cash_closing.seller_id')
            ->leftJoinSub($ordersSubquery, 'order_totals', 'order_totals.cash_closing_id', '=', 'cash_closing.id')
            ->select(
                'cash_closing.seller_id',
                'users.username',
                DB::raw('COUNT(DISTINCT cash_closing.id) as cash_closures_count'),
                DB::raw('SUM(total_usd) as total_usd_seller_native'),
                DB::raw('SUM(total_cop) as total_cop_seller_native'),
                DB::raw('SUM(total_bs) as total_bs_seller_native'),
                DB::raw('SUM(total_sales) as total_sales_seller'),
                DB::raw('SUM(usd_credit) as total_credits_seller'),
                // Recogemos las sumas históricas del JOIN
                DB::raw('SUM(COALESCE(order_totals.total_bs_usd_hist, 0)) as total_bs_usd_hist'),
                DB::raw('SUM(COALESCE(order_totals.total_cop_usd_hist, 0)) as total_cop_usd_hist')
            )
            ->groupBy('cash_closing.seller_id', 'users.username')
            ->orderByDesc('total_sales_seller')
            ->get();

        $totalSalesBs = 0.0;
        $totalSalesUsd = 0.0;
        $totalSalesCop = 0.0;
        $totalSalesBsInUSD = 0.0;
        $totalSalesGlobalCopInUsd = 0.0;
        $totalSalesGlobal = 0.0;
        $totalSalesCredits = 0.0;

        $summaryData = $sellerSummary->map(function ($summary) use (&$totalSalesBs, &$totalSalesUsd, &$totalSalesCop, &$totalSalesBsInUSD, &$totalSalesGlobalCopInUsd, &$totalSalesGlobal, &$totalSalesCredits) {

            // Ya no usamos tasas actuales, usamos el valor histórico traído del JOIN
            $bsInUsd = (float) $summary->total_bs_usd_hist;
            $copInUsd = (float) $summary->total_cop_usd_hist;

            $totalSalesBs += (float) $summary->total_bs_seller_native;
            $totalSalesUsd += (float) $summary->total_usd_seller_native;
            $totalSalesCop += (float) $summary->total_cop_seller_native;
            $totalSalesBsInUSD += $bsInUsd;
            $totalSalesGlobalCopInUsd += $copInUsd;
            $totalSalesGlobal += (float) $summary->total_sales_seller;
            $totalSalesCredits += (float) $summary->total_credits_seller;

            return [
                'seller_id' => $summary->seller_id,
                'seller_name' => $summary->username,
                'closures_count' => $summary->cash_closures_count,
                'total_sales' => number_format((float)$summary->total_sales_seller, 2, ',', '.'),
                'total_usd' => number_format((float)$summary->total_usd_seller_native, 2, ',', '.'),
                'total_cop' => number_format((float)$summary->total_cop_seller_native, 0, ',', '.'),
                'total_bs' => number_format((float)$summary->total_bs_seller_native, 2, ',', '.'),
                'total_credits' => number_format((float)$summary->total_credits_seller, 2, ',', '.'),

                'total_bs_in_usd' => number_format((float)$bsInUsd, 2, ',', '.'),
                'total_cop_in_usd' => number_format((float)$copInUsd, 2, ',', '.'),
            ];
        });

        return [
            'summary' => $summaryData,
            'totalSalesBs' => number_format((float)$totalSalesBs, 2, ',', '.'),
            'totalSalesUsd' => number_format((float)$totalSalesUsd, 2, ',', '.'),
            'totalSalesCop' => number_format((float)$totalSalesCop, 0, ',', '.'),
            'totalSalesBsInUSD' => number_format((float)$totalSalesBsInUSD, 2, ',', '.'),
            'totalSalesGlobalCopInUsd' => number_format((float)$totalSalesGlobalCopInUsd, 2, ',', '.'),
            'totalSalesGlobal' => number_format((float)$totalSalesGlobal, 2, ',', '.'),
            'totalSalesCredits' => number_format((float)$totalSalesCredits, 2, ',', '.'),
        ];
    }

    public function getCashClosingsAllSellers(array $dailyClosureIds): \Illuminate\Support\Collection
    {
        $cashClosings = CashClosing::query()
            ->whereIn('daily_closure_id', $dailyClosureIds)
            ->with('seller')
            ->get();

        $groupedBySeller = $cashClosings->groupBy('seller_id');
        return $groupedBySeller;
    }

}
