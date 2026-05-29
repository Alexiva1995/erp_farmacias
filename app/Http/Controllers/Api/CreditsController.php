<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;
use App\Services\Credits\CreditsQueryService;
use App\Services\Credits\CreditsActionService;
use App\Models\Credit;
use App\Helpers\ApiResponse;
use App\Http\Requests\Credits\UpdateCreditStatusRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class CreditsController extends Controller
{
    public function __construct(
        private CreditsQueryService $creditsQueryService,
        private CreditsActionService $creditsActionService,
    ) {
    }

    public function index(Request $request)
    {
        $query = $this->creditsQueryService->getFilteredQuery($request);

        $perPage = $request->input('itemsPerPage', 10);
        $paginatedResult = $query->paginate($perPage)->withQueryString();

        $credits = $paginatedResult->getCollection()->map(function ($credit) {
            if ($credit->credit_ids) {
                $credit->credit_ids = explode(',', $credit->credit_ids);
            } else {
                $credit->credit_ids = [];
            }
            return $credit;
        });

        return response()->json([
            'data' => $credits,
            'total' => $paginatedResult->total(),
        ]);
    }

    public function destroy(Request $request)
    {
        $request->validate([
            'credit_ids' => 'required|array',
            'credit_ids.*' => 'integer|exists:credits,id',
        ]);

        if (Auth::id() && \App\Models\User::find(Auth::id())?->role_id !== 1) {
            return ApiResponse::error('No autorizado. Solo administradores pueden eliminar créditos.', 403);
        }

        try {
            $this->creditsActionService->delete($request->input('credit_ids'));
            return ApiResponse::success(null, 'Crédito(s) eliminado(s) correctamente.');
        } catch (\Exception $e) {
            Log::error('Error al eliminar crédito:', ['error' => $e->getMessage()]);
            return ApiResponse::error('No se pudo eliminar el crédito: ' . $e->getMessage(), 500);
        }
    }

    public function updateCreditStatus(UpdateCreditStatusRequest $request, Credit $credit)
    {
        $validated = $request->validated();

        $success = $this->creditsActionService->updateStatus(
            $validated['ids'],
            $validated['status']
        );

        if ($success) {
            return response()->json([
                'message' => 'El estado de los créditos ha sido actualizado con éxito.',
            ]);
        }

        return response()->json([
            'message' => 'Error al actualizar el estado de los créditos.',
        ], 500);
    }


    public function completeCredits(Request $request)
    {
        try {
            $this->creditsActionService->complete($request);
        } catch (\Exception $e) {
            Log::error('Error al completar el pago:', ['error' => $e->getMessage()]);
            return ApiResponse::error('No se pudo completar la orden: ' . $e->getMessage(), 500);
        }
    }

    public function showDetails(Request $request)
    {
        $request->validate([
            'credit_ids' => 'required|array',
            'credit_ids.*' => 'integer|exists:credits,id',
        ]);

        $credits = Credit::with('client', 'order.details.product')
            ->whereIn('id', $request->input('credit_ids'))
            ->get();

        return response()->json($credits);
    }

    public function getPaymentHistory(Request $request)
    {
        $request->validate([
            'client_id' => 'required|integer|exists:clients,id',
        ]);

        $creditPayments = \App\Models\CreditPayment::with('seller')
            ->where('client_id', $request->input('client_id'))
            ->orderBy('payment_date', 'desc')
            ->get();

        $payments = [];
        foreach ($creditPayments as $cp) {
            $methodPayments = $cp->method_Payment;
            if (!is_array($methodPayments)) {
                $methodPayments = [];
            }
            $sellerName = $cp->seller?->username ?? 'N/A';
            $paymentDate = $cp->payment_date
                ? (is_object($cp->payment_date) ? $cp->payment_date->format('Y-m-d H:i:s') : (string) $cp->payment_date)
                : null;

            foreach ($methodPayments as $payment) {
                if (($payment['amount'] ?? 0) <= 0) {
                    continue;
                }

                $payments[] = [
                    'payment_date' => $paymentDate,
                    'amount' => $payment['amount'] ?? 0,
                    'method' => $payment['method'] ?? '',
                    'currency' => $payment['currency'] ?? 'USD',
                    'reference' => $payment['reference'] ?? '',
                    'seller' => $sellerName,
                ];
            }
        }

        return response()->json($payments);
    }

    public function payments(Request $request)
    {
        $search = $request->input('client');
        $sortBy = $request->input('sort_by', 'date');
        $orderBy = in_array($request->input('order_by'), ['asc', 'desc']) ? $request->input('order_by') : 'desc';
        $itemsPerPage = (int) $request->input('items_per_page', 10);

        if (DB::getDriverName() === 'sqlite') {
            $creditPayments = \App\Models\CreditPayment::with(['client', 'seller'])->get();
            $payments = [];

            foreach ($creditPayments as $cp) {
                $methodPayments = $cp->method_Payment;
                if (!is_array($methodPayments)) {
                    $methodPayments = [];
                }

                $clientName = $cp->client ? $cp->client->name . ' ' . $cp->client->last_name : 'N/A';
                $sellerName = $cp->seller ? $cp->seller->username : 'N/A';
                $paymentDate = $cp->payment_date
                    ? (is_object($cp->payment_date) ? $cp->payment_date->format('Y-m-d H:i:s') : (string) $cp->payment_date)
                    : null;

                foreach ($methodPayments as $payment) {
                    $amount = (float) ($payment['amount'] ?? 0);
                    if ($amount <= 0) {
                        continue;
                    }

                    $payments[] = [
                        'id' => $cp->id,
                        'amount' => $amount,
                        'method' => $payment['method'] ?? '',
                        'currency' => $payment['currency'] ?? 'USD',
                        'reference' => $payment['reference'] ?? 'N/A',
                        'date' => $paymentDate,
                        'seller' => $sellerName,
                        'client' => $clientName,
                    ];
                }
            }

            $paymentsCollection = collect($payments);

            if ($currency = $request->input('currency')) {
                $paymentsCollection = $paymentsCollection->where('currency', $currency);
            }

            if ($search) {
                $paymentsCollection = $paymentsCollection->filter(function ($item) use ($search) {
                    return stripos($item['client'], $search) !== false;
                });
            }

            if ($date = $request->input('date')) {
                $paymentsCollection = $paymentsCollection->filter(function ($item) use ($date) {
                    return substr($item['date'], 0, 10) === $date;
                });
            }

            $sortKey = $sortBy;
            if ($sortKey === 'date') $sortKey = 'date';
            
            $paymentsCollection = $orderBy === 'desc'
                ? $paymentsCollection->sortByDesc($sortKey)
                : $paymentsCollection->sortBy($sortKey);

            $currentPage = \Illuminate\Pagination\LengthAwarePaginator::resolveCurrentPage();
            $paginated = new \Illuminate\Pagination\LengthAwarePaginator(
                $paymentsCollection->forPage($currentPage, $itemsPerPage)->values()->all(),
                $paymentsCollection->count(),
                $itemsPerPage,
                $currentPage,
                ['path' => \Illuminate\Pagination\LengthAwarePaginator::resolveCurrentPath()]
            );

            return response()->json([
                'data' => $paginated->items(),
                'total' => $paginated->total(),
            ]);
        }

        $query = DB::table('credit_payments as cp')
            ->crossJoin(DB::raw('JSON_TABLE(
            cp.method_Payment,
            "$[*]" COLUMNS (
                amount DECIMAL(15,2) PATH "$.amount",
                method VARCHAR(50) PATH "$.method",
                currency VARCHAR(10) PATH "$.currency",
                reference VARCHAR(100) PATH "$.reference"
            )
        ) AS payment'))
            ->join('clients', 'cp.client_id', '=', 'clients.id')
            ->join('users as seller', 'cp.seller_id', '=', 'seller.id')
            ->select([
                'cp.id',
                'payment.amount',
                'payment.method',
                'payment.currency',
                'payment.reference',
                'cp.payment_date',
                DB::raw("CONCAT(clients.name, ' ', clients.last_name) as client"),
                'seller.username as seller',
            ])
            ->where('payment.amount', '>', 0);

        if ($currency = $request->input('currency')) {
            $query->where('payment.currency', $currency);
        }

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('clients.name', 'LIKE', "%{$search}%")
                    ->orWhere('clients.last_name', 'LIKE', "%{$search}%");
            });
        }

        if ($date = $request->input('date')) {
            $query->whereDate('cp.payment_date', $date);
        }

        switch ($sortBy) {
            case 'client':
                $query->orderBy(DB::raw("CONCAT(clients.name, ' ', clients.last_name)"), $orderBy);
                break;
            case 'seller':
                $query->orderBy('seller.username', $orderBy);
                break;
            case 'currency':
                $query->orderBy('payment.currency', $orderBy);
                break;
            case 'amount':
                $query->orderBy('payment.amount', $orderBy);
                break;
            case 'method':
                $query->orderBy('payment.method', $orderBy);
                break;
            case 'reference':
                $query->orderBy('payment.reference', $orderBy);
                break;
            case 'date':
            default:
                $query->orderBy('cp.payment_date', $orderBy);
                break;
        }

        $payments = $query->paginate($itemsPerPage)
            ->through(function ($row) {
                return [
                    'id' => $row->id,
                    'amount' => $row->amount,
                    'method' => $row->method,
                    'currency' => $row->currency,
                    'reference' => $row->reference ?? 'N/A',
                    'date' => $row->payment_date,
                    'seller' => $row->seller,
                    'client' => $row->client,
                ];
            });

        return response()->json([
            'data' => $payments->items(),
            'total' => $payments->total(),
        ]);
    }
}
