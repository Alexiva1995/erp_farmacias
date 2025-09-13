<?php

namespace App\Http\Controllers\Api;

use App\Contracts\Transaction;
use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
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
            'items' => $results['paginator']->items(),
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
}
