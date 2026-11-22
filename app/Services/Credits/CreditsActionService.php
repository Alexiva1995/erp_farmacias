<?php

declare(strict_types=1);

namespace App\Services\Credits;

use App\Models\CashClosing;
use App\Models\Credit;
use App\Models\CreditPayment;
use App\Models\CreditPaymentAllocation;
use App\Services\Resources\ResourceService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CreditsActionService
{
    public function __construct(private ResourceService $resourceService)
    {
    }

    /**
     * Elimina lógicamente créditos sin abonos previos, registrando auditoría.
     */
    public function delete(array $creditIds, ?string $reason = null): bool
    {
        try {
            DB::beginTransaction();

            $credits = Credit::whereIn('id', $creditIds)->lockForUpdate()->get();

            foreach ($credits as $credit) {
                $hasPartialPayments = (float) $credit->pending_amount < (float) $credit->credit_amount;
                $hasAllocations = $credit->allocations()->exists();

                if ($hasPartialPayments || $hasAllocations) {
                    throw new \RuntimeException(
                        "No se puede eliminar el crédito #{$credit->id} porque ya tiene abonos registrados. Anule primero los abonos."
                    );
                }

                $credit->voided_by = Auth::id();
                $credit->void_reason = $reason ?? 'Eliminación administrativa de crédito';
                $credit->save();
                $credit->delete();
            }

            DB::commit();
            return true;
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error al eliminar créditos: ' . $e->getMessage(), [
                'credit_ids' => $creditIds,
            ]);
            throw $e;
        }
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

    /**
     * Registra un abono de crédito, distribuye el monto en FIFO (o créditos seleccionados)
     * dejando trazabilidad en credit_payment_allocations y actualizando la caja abierta.
     */
    public function complete(Request $request): bool
    {
        try {
            DB::beginTransaction();

            $moneyReturns = (float) ($request->changeAmount ?? 0);
            $changeAmountUSD = (float) ($request->changeAmountUSD ?? 0);
            $sellerId = Auth::id();

            $creditsQuery = Credit::where('client_id', $request->clientId)
                ->where('status', 'Active')
                ->where('pending_amount', '>', 0)
                ->where(function ($q) {
                    $q->whereNull('credit_type')
                        ->orWhere('credit_type', '!=', 'cashea');
                })
                ->orderBy('credit_date')
                ->orderBy('id')
                ->lockForUpdate();

            $selectedCreditIds = $request->input('credit_ids');
            if (is_array($selectedCreditIds) && count($selectedCreditIds) > 0) {
                $creditsQuery->whereIn('id', $selectedCreditIds);
            }

            $creditosPendientes = $creditsQuery->get();

            if ($creditosPendientes->isEmpty()) {
                throw new \RuntimeException('El cliente no tiene créditos pendientes activos para abonar.');
            }

            $bsToUsdRate = (float) ($this->resourceService->getExchangeRate('BS') ?: 1);
            $copToUsdRate = (float) ($this->resourceService->getExchangeRate('COP') ?: 1);
            $copChangeRate = (float) ($this->resourceService->getExchangeRate('COPC') ?: $copToUsdRate);

            $currentCash = CashClosing::where('status', CashClosing::OPEN)
                ->where('seller_id', $sellerId)
                ->first();

            if (!$currentCash) {
                $currentCash = CashClosing::create([
                    'seller_id' => $sellerId,
                    'status' => CashClosing::OPEN,
                    'closing_date' => Carbon::now(),
                ]);
            }

            $copChangeGiven = ($changeAmountUSD > 0 && $moneyReturns > 0)
                ? round($changeAmountUSD * $copChangeRate, 2)
                : ($moneyReturns > 0 ? $moneyReturns : 0.0);

            $creditPayment = CreditPayment::create([
                'client_id' => $request->clientId,
                'seller_id' => $sellerId,
                'cash_closing_id' => $currentCash->id,
                'method_Payment' => $request->payments,
                'money_returns' => $moneyReturns,
                'change_amount_usd' => $changeAmountUSD,
                'change_amount_cop' => $copChangeGiven,
                'payment_date' => Carbon::now(),
                'status' => 'approved',
                'exchange_rate_applied' => $bsToUsdRate,
                'amount_usd_equivalent' => 0,
            ]);

            $totalAppliedUsd = 0.0;
            $allocationsByCredit = [];

            foreach ($request->payments as $paymentItem) {
                $rawAmount = (float) ($paymentItem['amount'] ?? 0);
                $currency = strtoupper((string) ($paymentItem['currency'] ?? 'USD'));
                if ($rawAmount <= 0) {
                    continue;
                }

                $remainingPaymentUsd = $this->convertToUsd($rawAmount, $currency, $bsToUsdRate, $copToUsdRate);
                if ($remainingPaymentUsd <= 0) {
                    continue;
                }

                foreach ($creditosPendientes as $credito) {
                    if ($remainingPaymentUsd <= 0) {
                        break;
                    }

                    $saldoPendiente = (float) $credito->pending_amount;
                    if ($saldoPendiente <= 0) {
                        continue;
                    }

                    $montoAplicadoUsd = round(min($remainingPaymentUsd, $saldoPendiente), 2);
                    if ($montoAplicadoUsd <= 0) {
                        continue;
                    }

                    $credito->pending_amount = max(0, round($saldoPendiente - $montoAplicadoUsd, 2));
                    if ($credito->pending_amount <= 0.009) {
                        $credito->pending_amount = 0;
                        $credito->status = 'Paid';
                    }
                    $credito->save();

                    $remainingPaymentUsd = round($remainingPaymentUsd - $montoAplicadoUsd, 2);
                    $totalAppliedUsd = round($totalAppliedUsd + $montoAplicadoUsd, 2);

                    $originalApplied = $this->convertFromUsd($montoAplicadoUsd, $currency, $bsToUsdRate, $copToUsdRate);

                    if (!isset($allocationsByCredit[$credito->id])) {
                        $allocationsByCredit[$credito->id] = [
                            'credit_id' => $credito->id,
                            'amount_allocated_usd' => 0.0,
                            'amount_allocated_original' => 0.0,
                            'currency' => $currency,
                        ];
                    }
                    $allocationsByCredit[$credito->id]['amount_allocated_usd'] = round(
                        $allocationsByCredit[$credito->id]['amount_allocated_usd'] + $montoAplicadoUsd,
                        2
                    );
                    $allocationsByCredit[$credito->id]['amount_allocated_original'] = round(
                        $allocationsByCredit[$credito->id]['amount_allocated_original'] + $originalApplied,
                        2
                    );
                }
            }

            if ($totalAppliedUsd <= 0) {
                throw new \RuntimeException('El monto del abono no pudo aplicarse a ningún crédito pendiente.');
            }

            foreach ($allocationsByCredit as $allocation) {
                CreditPaymentAllocation::create([
                    'credit_payment_id' => $creditPayment->id,
                    'credit_id' => $allocation['credit_id'],
                    'amount_allocated_usd' => $allocation['amount_allocated_usd'],
                    'amount_allocated_original' => $allocation['amount_allocated_original'],
                    'currency' => $allocation['currency'],
                    'early_payment_discount_applied' => 0,
                ]);
            }

            $creditPayment->amount_usd_equivalent = $totalAppliedUsd;
            if (count($allocationsByCredit) === 1) {
                $onlyAllocation = reset($allocationsByCredit);
                $creditPayment->credit_id = $onlyAllocation['credit_id'];
            }
            $creditPayment->save();

            foreach ($request->payments as $payment) {
                $method = $payment['method'] ?? null;
                $amount = (float) ($payment['amount'] ?? 0);

                if ($method && $amount > 0) {
                    $this->applyPaymentMethodToCashClosing($currentCash, $method, $amount, 1);
                }
            }

            if ($changeAmountUSD > 0 && $moneyReturns > 0) {
                $currentCash->cop_conversion_payment_credit += $copChangeGiven;
                $currentCash->cop_cash_payment_credit -= $copChangeGiven;
            } elseif ($moneyReturns > 0) {
                $currentCash->cop_cash_payment_credit -= $moneyReturns;
            } elseif ($changeAmountUSD > 0) {
                $currentCash->usd_cash_payment_credit -= $changeAmountUSD;
            }

            $currentCash->recalculateTotals();

            DB::commit();
            return true;
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error al completar el pago de crédito: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
            ]);
            throw $e;
        }
    }

    /**
     * Anula un abono de crédito sin borrarlo físicamente, restaurando el saldo exacto
     * en los créditos afectados y revirtiendo el impacto en caja solo si sigue abierta.
     */
    public function deletePayment(CreditPayment $payment, ?string $reason = null): bool
    {
        DB::beginTransaction();
        try {
            if ($payment->voided_at !== null || $payment->status === 'voided') {
                throw new \RuntimeException('Este abono ya fue anulado previamente.');
            }

            $methodPayments = $payment->method_Payment;
            if (is_string($methodPayments)) {
                $methodPayments = json_decode($methodPayments, true) ?: [];
            }
            if (!is_array($methodPayments)) {
                $methodPayments = [];
            }

            $allocations = $payment->allocations()->get();

            if ($allocations->isNotEmpty()) {
                foreach ($allocations as $allocation) {
                    $credit = Credit::withTrashed()->find($allocation->credit_id);
                    if (!$credit) {
                        continue;
                    }
                    $credit->pending_amount = min(
                        (float) $credit->credit_amount,
                        round((float) $credit->pending_amount + (float) $allocation->amount_allocated_usd, 2)
                    );
                    if ($credit->pending_amount > 0 && $credit->status === 'Paid') {
                        $credit->status = 'Active';
                    }
                    $credit->save();
                }
            } else {
                // Respaldo para abonos históricos creados antes de registrar allocations
                $totalUsdRestored = (float) ($payment->amount_usd_equivalent ?? 0);
                if ($totalUsdRestored <= 0) {
                    $bsRate = (float) ($payment->exchange_rate_applied ?: $this->resourceService->getExchangeRate('BS') ?: 1);
                    $copRate = (float) ($this->resourceService->getExchangeRate('COP') ?: 1);

                    foreach ($methodPayments as $pay) {
                        $amt = (float) ($pay['amount'] ?? 0);
                        $cur = strtoupper((string) ($pay['currency'] ?? 'USD'));
                        if ($amt <= 0) {
                            continue;
                        }
                        $totalUsdRestored += $this->convertToUsd($amt, $cur, $bsRate, $copRate);
                    }
                }

                $credits = Credit::where('client_id', $payment->client_id)
                    ->orderBy('id', 'desc')
                    ->get();

                $remainingToRestore = round($totalUsdRestored, 2);
                foreach ($credits as $credit) {
                    if ($remainingToRestore <= 0) {
                        break;
                    }

                    $maxCanRestore = round((float) $credit->credit_amount - (float) $credit->pending_amount, 2);
                    if ($maxCanRestore <= 0) {
                        continue;
                    }

                    $restoreForThisCredit = min($remainingToRestore, $maxCanRestore);
                    $credit->pending_amount = round((float) $credit->pending_amount + $restoreForThisCredit, 2);
                    if ($credit->pending_amount > 0 && $credit->status === 'Paid') {
                        $credit->status = 'Active';
                    }
                    $credit->save();
                    $remainingToRestore = round($remainingToRestore - $restoreForThisCredit, 2);
                }
            }

            // Solo revertir montos en CashClosing si la caja vinculada sigue abierta
            if ($payment->cash_closing_id) {
                $cash = CashClosing::find($payment->cash_closing_id);
                if ($cash && $cash->status === CashClosing::OPEN) {
                    foreach ($methodPayments as $pay) {
                        $method = $pay['method'] ?? null;
                        $amount = (float) ($pay['amount'] ?? 0);
                        if ($method && $amount > 0) {
                            $this->applyPaymentMethodToCashClosing($cash, $method, $amount, -1);
                        }
                    }

                    $changeUsd = (float) ($payment->change_amount_usd ?? 0);
                    $changeCop = (float) ($payment->change_amount_cop ?? 0);
                    $moneyReturns = (float) ($payment->money_returns ?? 0);

                    if ($changeUsd > 0 && $changeCop > 0) {
                        $cash->cop_conversion_payment_credit = max(0, $cash->cop_conversion_payment_credit - $changeCop);
                        $cash->cop_cash_payment_credit += $changeCop;
                    } elseif ($moneyReturns > 0) {
                        $cash->cop_cash_payment_credit += $moneyReturns;
                    } elseif ($changeUsd > 0) {
                        $cash->usd_cash_payment_credit += $changeUsd;
                    }

                    $cash->recalculateTotals();
                }
            }

            $payment->status = 'voided';
            $payment->voided_by = Auth::id();
            $payment->voided_at = Carbon::now();
            $payment->void_reason = $reason ?? 'Anulación administrativa de abono';
            $payment->save();

            DB::commit();
            return true;
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('Error al anular abono de crédito: ' . $e->getMessage());
            throw $e;
        }
    }

    private function convertToUsd(float $amount, string $currency, float $bsRate, float $copRate): float
    {
        if ($currency === 'BS') {
            return $bsRate > 0 ? round($amount / $bsRate, 4) : 0.0;
        }
        if ($currency === 'COP') {
            return $copRate > 0 ? round($amount / $copRate, 4) : 0.0;
        }

        return $amount;
    }

    private function convertFromUsd(float $amountUsd, string $currency, float $bsRate, float $copRate): float
    {
        if ($currency === 'BS') {
            return round($amountUsd * $bsRate, 2);
        }
        if ($currency === 'COP') {
            return round($amountUsd * $copRate, 2);
        }

        return round($amountUsd, 2);
    }

    private function applyPaymentMethodToCashClosing(CashClosing $cash, string $method, float $amount, int $sign = 1): void
    {
        $delta = $amount * $sign;

        switch ($method) {
            case 'cash_usd':
                $cash->usd_cash_payment_credit = max(0, (float) $cash->usd_cash_payment_credit + $delta);
                break;
            case 'binance':
                $cash->usd_binance_payment_credit = max(0, (float) $cash->usd_binance_payment_credit + $delta);
                break;
            case 'paypal':
                $cash->usd_paypal_payment_credit = max(0, (float) $cash->usd_paypal_payment_credit + $delta);
                break;
            case 'zelle':
            case 'usd_transfer':
            case 'bank_transfer_usd':
                $cash->usd_transfer_payment_credit = max(0, (float) $cash->usd_transfer_payment_credit + $delta);
                break;
            case 'cash_bs':
                $cash->bs_cash_payment_credit = max(0, (float) $cash->bs_cash_payment_credit + $delta);
                break;
            case 'mobile_payment':
                $cash->bs_mobile_payment_credit = max(0, (float) $cash->bs_mobile_payment_credit + $delta);
                break;
            case 'bank_transfer_bs':
                $cash->bs_transfer_payment_credit = max(0, (float) $cash->bs_transfer_payment_credit + $delta);
                break;
            case 'card':
            case 'debit_card':
            case 'credit_card':
                $cash->bs_card_payment_credit = max(0, (float) $cash->bs_card_payment_credit + $delta);
                break;
            case 'cash_cop':
                $cash->cop_cash_payment_credit = max(0, (float) $cash->cop_cash_payment_credit + $delta);
                break;
            case 'bank_transfer':
                $cash->cop_transfer_payment_credit = max(0, (float) $cash->cop_transfer_payment_credit + $delta);
                break;
        }
    }
}
