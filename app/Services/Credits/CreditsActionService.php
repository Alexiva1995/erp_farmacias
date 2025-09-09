<?php

namespace App\Services\Credits;


use App\Models\Credit;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log; 
use Carbon\Carbon;
use App\Models\CreditPayment;
use App\Models\CashClosing;
use Illuminate\Support\Facades\Auth;
use App\Services\Resources\ResourceService;

class CreditsActionService
{

    public function __construct(private ResourceService $resourceService)
    {
    }

    public function updateStatus(array $creditIds, string $status): bool
    {
        try {
            DB::beginTransaction();

            Credit::whereIn('id', $creditIds)
                ->update(['status' => $status]);

            DB::commit();

            return true;
        } catch (\Exception $e) {
            DB::rollBack();
            return false;
        }
    }

    public function complete(Request $request): bool
    {
        try {
            DB::beginTransaction();
            $money_returns = (isset($request->changeAmount)) ? $request->changeAmount : 0;

            //  $sellerId = Auth::id();
            $sellerId = 3; //para realizar pruebas
            $creditosPagados = [];
            $creditosPendientes = Credit::where('client_id', $request->clientId)->where('status', 'Active')->orderBy('created_at')->get();

            foreach ($request->payments as $paymend) {
                $montoRestantePagoActual = $paymend['amount'];
                $montoRestantePagoActual = (float)$montoRestantePagoActual;
                
                $rates = $this->resourceService->getAllExchangeRate();
                $ratesArray = $rates->pluck('rate', 'currency_code')->toArray();
                 
                if ($paymend['currency'] == 'BS') {
                    $bsToUsdRate = (float) ($ratesArray['BS'] ?? 0);
                    if ($bsToUsdRate > 0) {
                        $montoRestantePagoActual /= $bsToUsdRate;
                    }
                }else if ($paymend['currency'] == 'COP') {
                     $copToUsdRate = (float) ($ratesArray['COP'] ?? 0);
                    if ($copToUsdRate > 0) {
                        $montoRestantePagoActual /= $copToUsdRate;
                    }
                }
                
                if ($montoRestantePagoActual <= 0) {
                    continue;
                }

                foreach ($creditosPendientes as $credito) {
                    if ($montoRestantePagoActual <= 0) {
                        break; 
                    }

                    $credito->refresh();

                    if ($credito->pending_amount <= 0) {
                        continue;
                    }

                    $saldoPendiente = $credito->pending_amount;
                    $montoAplicado = min($montoRestantePagoActual, $saldoPendiente);

                    $credito->pending_amount -= $montoAplicado;

                    if ($credito->pending_amount <= 0) {
                        $credito->status = 'Paid';
                    }
                    $credito->save();
                    $montoRestantePagoActual -= $montoAplicado;

                    if (!in_array($credito->id, $creditosPagados)) {
                        $creditosPagados[] = $credito->id;
                    }
                }
            }

            $current_cash = CashClosing::where('status', 'open')->where('seller_id', $sellerId)->first();
            if (!isset($current_cash)) {
                $current_cash = CashClosing::create([
                    'seller_id' => $sellerId,
                    'status' =>  'open',
                    'closing_date' => Carbon::now(),
                ]);
            }
                
            CreditPayment::create([
                'client_id'      => $request->clientId,
                'seller_id'       => $sellerId,
                'cash_closing_id'       => $current_cash->id,
                'method_Payment'       => $request->payments,
                'money_returns'       => $money_returns,
                'payment_date'   => Carbon::now(),
            ]);

            $current_cash->cop_conversion_payment_credit += $request->changeAmount ?? 0.00;

             foreach ($request->payments as $payment) {
                $method = $payment['method'] ?? null;
                $amount = $payment['amount'] ?? 0;

                if (isset($method)) {
                    switch ($method) {
                        case 'cash_usd':
                            $current_cash->usd_cash_payment_credit += $amount;
                            break;
                        case 'binance':
                            $current_cash->usd_binance_payment_credit += $amount;
                            break;
                        case 'paypal':
                            $current_cash->usd_paypal_payment_credit += $amount;
                            break;
                        case 'cash_bs':
                            $current_cash->bs_cash_payment_credit += $amount;
                            break;
                        case 'mobile_payment':
                            $current_cash->bs_mobile_payment_credit += $amount;
                            break;
                        case 'bank_transfer_bs':
                            $current_cash->bs_transfer_payment_credit += $amount;
                            break;
                        case 'card':
                            $current_cash->bs_card_payment_credit += $amount;
                            break;
                        case 'cash_cop':
                            $current_cash->cop_cash_payment_credit += $amount;
                            break;
                        case 'bank_transfer':
                            $current_cash->cop_transfer_payment_credit += $amount;
                            break;
                    }
                }
            }

            if (!isset($request->changeAmountUSD)) {
                if (isset($request->changeAmount)) {
                $current_cash->cop_cash -= $request->changeAmount;
                }
            }

            $current_cash->update();
            DB::commit();
            return true;

        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('Error al completar el pago: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
            ]);
            throw $e;
        }
    }
}
