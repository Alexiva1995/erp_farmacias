<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Contracts\Transaction;
use App\Exports\TransactionsExport;
use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Requests\Finances\AjusteBalanceRequest;
use App\Http\Requests\Finances\GetTransactionsRequest;
use App\Http\Resources\Finances\TransactionResource;
use App\Repositories\TransactionRepository;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;

class TransactionController extends Controller
{
    public function __construct(
        protected Transaction $transaction,
        protected TransactionRepository $transactionRepository,
    ) {
    }

    public function getAll(GetTransactionsRequest $request): JsonResponse
    {
        $results = $this->transaction->getAll($request->validated());

        return ApiResponse::success([
            'items' => TransactionResource::collection($results['paginator']->items()),
            'total' => $results['paginator']->total(),
            'previous_total_usd' => $results['previous_total_usd'],
        ]);
    }

    public function getByType(Request $request): JsonResponse
    {
        $data = [
            'start_date' => $request->query('start_date'),
            'end_date'   => $request->query('end_date'),
            'currency'   => $request->query('currency'),
            'detailed'   => $request->boolean('detailed'),
        ];

        $results = $this->transaction->getByType($data);

        return ApiResponse::success($results);
    }

    public function getWallets(Request $request): JsonResponse
    {
        $data = [
            'start_date' => $request->query('start_date'),
            'end_date'   => $request->query('end_date'),
        ];

        $results = $this->transaction->getWallets($data);

        return ApiResponse::success($results);
    }

    public function getIncomeSummary(Request $request): JsonResponse
    {
        $data = [
            'start_date' => $request->query('start_date'),
            'end_date'   => $request->query('end_date'),
        ];

        $results = $this->transaction->getIncomeSummary($data);

        return ApiResponse::success($results);
    }

    public function adjustBalance(AjusteBalanceRequest $request): JsonResponse
    {
        $this->transaction->adjustBalance($request->validated());

        return ApiResponse::success(null, 'Saldo ajustado correctamente');
    }

    /**
     * Exportar transacciones a Excel con los filtros del flujo de caja.
     */
    public function exportExcel(Request $request)
    {
        $data = [
            'start_date' => $request->query('start_date'),
            'end_date'   => $request->query('end_date'),
            'currency'   => $request->query('currency'),
            'detailed'   => $request->boolean('detailed'),
            'option'     => $request->query('option'),
        ];

        $query = $this->transactionRepository->getExportQuery($data);

        $filename = 'flujo-caja-' . now()->format('Y-m-d_H-i') . '.xlsx';

        return Excel::download(new TransactionsExport($query), $filename);
    }

    /**
     * Retorna el estado de cierres de caja y las tasas de cambio actuales.
     */
    public function getCashStatus(): JsonResponse
    {
        $closingStatus = $this->transactionRepository->getCashClosingStatus();
        $rates = $this->transactionRepository->getCurrentRates();

        return ApiResponse::success([
            'closing_status' => $closingStatus,
            'rates' => $rates,
        ]);
    }
}
