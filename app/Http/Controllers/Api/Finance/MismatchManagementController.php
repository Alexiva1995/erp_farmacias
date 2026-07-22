<?php

namespace App\Http\Controllers\Api\Finance;

use App\Http\Controllers\Controller;
use App\Models\CashClosing;
use App\Models\Transaction;
use App\Models\CashFlow;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class MismatchManagementController extends Controller
{
    public function acceptMismatch(Request $request)
    {
        // Solo administradores o supervisores
        if ($request->user()->role_id !== 1) {
            return response()->json(['message' => 'Acceso denegado. Solo administradores pueden aceptar descuadres.'], 403);
        }

        $request->validate([
            'cash_closing_id' => 'required|exists:cash_closing,id',
            'currency' => 'required|in:USD,COP,BS',
            'mismatch_type' => 'required|string', // usd, cop, bs_card, bs_mobile, credit, cop_transfer
            'difference' => 'required|numeric', // Puede ser positivo (sobrante) o negativo (faltante)
        ]);

        $closing = CashClosing::findOrFail($request->cash_closing_id);
        $diff = (float) $request->difference;
        $currency = $request->currency;
        $type = $request->mismatch_type;

        DB::beginTransaction();
        try {
            $movementType = $diff >= 0 ? 'IN' : 'OUT';
            $absDiff = abs($diff);
            
            // 1. Registro de la transacción en el flujo contable (transactions)
            $transaction = Transaction::create([
                'user_id' => $request->user()->id,
                'exchange_rate' => $currency === 'BS' ? $closing->exchange_rate : ($currency === 'COP' ? $closing->cop_exchange_rate : 1.0000),
                'description' => "Ajuste de descuadre consolidado ({$movementType}) - Caja #{$closing->id} ({$closing->seller->username}) - Moneda: {$currency}",
                'currency' => $currency,
                'type' => $this->mapMismatchToTransactionType($type),
                'amount' => $absDiff,
                'movement_type' => $movementType,
                'transaction_date' => now()->toDateString(),
            ]);

            // 2. Registro en el flujo de caja diario (cash_flow)
            $flowField = $this->mapMismatchToFlowField($type);
            if ($flowField) {
                CashFlow::create([
                    'cash_closing_id' => $closing->id,
                    'flow_date' => now()->toDateString(),
                    $flowField => $diff, // Guarda el valor con signo para sumar o restar al acumulado
                ]);
            }

            // 3. Modificaciones sobre los datos originales del cierre
            $currentMismatches = is_string($closing->blind_mismatches)
                ? json_decode($closing->blind_mismatches, true)
                : ($closing->blind_mismatches ?? []);

            if ($diff > 0) {
                // SOBRANTE: Ajustar la data original incrementando el teórico para que coincida con el físico
                $this->adjustTeoricoSobrante($closing, $type, $absDiff);
                $closing->blind_note = $closing->blind_note . " | Sobrante de " . number_format($absDiff, 2) . " {$currency} ACEPTADO y Ajustado en Sistema.";
            } else {
                // FALTANTE: Dejar data original inalterada pero registrar glosa de descuento aceptado
                $closing->blind_note = $closing->blind_note . " | Faltante de " . number_format($absDiff, 2) . " {$currency} ACEPTADO y descontado de flujo.";
            }

            // Remover el elemento resuelto de los descuadres pendientes
            $updatedMismatches = array_values(array_filter($currentMismatches, function($m) use ($type) {
                return $m !== $type && $m !== $this->mapTypeToMismatchString($type);
            }));

            $closing->blind_mismatches = json_encode($updatedMismatches);
            $closing->save();
            $closing->recalculateTotals();

            DB::commit();

            return response()->json([
                'status' => 'success',
                'message' => 'Descuadre procesado y conciliado con éxito.',
                'data' => $closing->refresh()
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("Error al aceptar descuadre: " . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Error interno al procesar el ajuste de caja: ' . $e->getMessage()
            ], 500);
        }
    }

    private function mapMismatchToTransactionType($type)
    {
        if (str_contains($type, 'card')) return 'CARD';
        if (str_contains($type, 'transfer') || str_contains($type, 'mobile') || str_contains($type, 'paypal') || str_contains($type, 'binance')) return 'TRANSFER';
        if ($type === 'credit') return 'CREDIT';
        return 'CASH';
    }

    private function mapMismatchToFlowField($type)
    {
        $map = [
            'usd' => 'amount_usd',
            'usd_cash' => 'amount_usd',
            'usd_transfer' => 'amount_usd',
            'usd_binance' => 'amount_binance',
            'usd_paypal' => 'amount_paypal',
            'cop' => 'amount_cop',
            'cop_cash' => 'amount_cop',
            'cop_transfer' => 'amount_bancolombia',
            'bs_cash' => 'amount_bs_cash',
            'bs_mobile' => 'amount_bs_mobile',
            'bs_transfer' => 'amount_bs_transfer',
            'bs_card' => 'amount_bs_card',
            'credit' => 'amount_credit_pending',
        ];
        return $map[$type] ?? null;
    }

    private function mapTypeToMismatchString($type)
    {
        if (str_contains($type, 'cop')) return 'cop';
        if (str_contains($type, 'usd')) return 'usd';
        if (str_contains($type, 'bs_card')) return 'bs_card';
        if (str_contains($type, 'bs_mobile')) return 'bs_mobile';
        return $type;
    }

    private function adjustTeoricoSobrante(CashClosing $closing, $type, $amount)
    {
        // Incrementamos el valor teórico para emparejarlo con el declarado físico reportado
        if ($type === 'usd' || $type === 'usd_cash') {
            $closing->usd_cash += $amount;
            $closing->usd_delivered += $amount;
        } else if ($type === 'cop' || $type === 'cop_cash') {
            $closing->cop_cash += $amount;
            $closing->cop_delivered += $amount;
        } else if ($type === 'bs_cash') {
            $closing->bs_cash += $amount;
            $closing->bs_delivered += $amount;
        } else if ($type === 'bs_card') {
            $closing->bs_card_debito += $amount;
        } else if ($type === 'bs_mobile') {
            $closing->bs_mobile += $amount;
        } else if ($type === 'cop_transfer') {
            $closing->cop_transfer += $amount;
        } else if ($type === 'usd_transfer') {
            $closing->usd_transfer += $amount;
        } else if ($type === 'credit') {
            $closing->usd_credit += $amount;
        }
    }
}
