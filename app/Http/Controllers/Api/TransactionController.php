<?php

namespace App\Http\Controllers\Api;

use App\Contracts\Transaction;
use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Requests\Finances\AjusteBalanceRequest;
use App\Http\Resources\Finances\TransactionResource;
use Illuminate\Http\Request;

class TransactionController extends Controller
{
    public function __construct(protected Transaction $transaction)
    {
    }

    public function getAll(Request $request)
    {
        $data = [
            'start_date' => $request->start_date,
            'end_date' => $request->end_date,
            'currency' => $request->currency,
            'detailed' => $request->boolean('detailed'),
            'option' => $request->option,
            'per_page' => $request->per_page,
            'page' => $request->page
        ];

        $results = $this->transaction->getAll($data);
        return ApiResponse::success([
            'items' => TransactionResource::collection($results['paginator']->items()),
            'total' => $results['paginator']->total(),
            'previous_total_usd' => $results['previous_total_usd']
        ]);
    }

    public function getByType(Request $request)
    {
        $data = [
            'start_date' => $request->start_date,
            'end_date' => $request->end_date,
            'currency' => $request->currency,
            'detailed' => $request->detailed,
        ];

        $results = $this->transaction->getByType($data);
        return ApiResponse::success($results);
    }

    public function getWallets(Request $request)
    {
        $data = [
            'start_date' => $request->start_date,
            'end_date'   => $request->end_date,
        ];

        $results = $this->transaction->getWallets($data);
        return ApiResponse::success($results);
    }

    public function getIncomeSummary(Request $request)
    {
        $data = [
            'start_date' => $request->start_date,
            'end_date'   => $request->end_date,
        ];

        $results = $this->transaction->getIncomeSummary($data);
        return ApiResponse::success($results);
    }

    public function adjustBalance(AjusteBalanceRequest $request)
    {
        $this->transaction->adjustBalance($request->validated());
        return ApiResponse::success(null, 'Saldo ajustado correctamente');
    }
}
