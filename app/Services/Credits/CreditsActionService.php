<?php

namespace App\Services\Credits;


use App\Models\Credit;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log; 
use Carbon\Carbon;
use App\Models\CreditPayment;
use App\Models\CashClosing;
use Illuminate\Support\Facades\Auth;

class CreditsActionService
{

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
          
            $money_returns = (isset($request->changeAmount)) ? $request->changeAmount : 0;

            //  $sellerId = Auth::id();
            $sellerId = 3; //para realizar pruebas

             $openCashRegisterClosing = CashClosing::where('seller_id', $sellerId)
                ->where('status', CashClosing::OPEN)
                ->first();
                
            dd($request);
            CreditPayment::create([
                'client_id'      => $request->clientId,
                'seller_id'       => $sellerId,
                'cash_closing_id'       => $openCashRegisterClosing->id,
                'method_Payment'       => $request->payments,
                'money_returns'       => $money_returns,
                'payment_date'   => Carbon::now(),
            ]);

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
