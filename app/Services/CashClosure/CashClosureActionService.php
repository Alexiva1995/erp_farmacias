<?php

namespace App\Services\CashClosure;

use App\Models\CashClosing;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Exception;

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
}
