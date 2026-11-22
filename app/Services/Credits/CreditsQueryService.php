<?php

declare(strict_types=1);

namespace App\Services\Credits;

use App\Models\Credit;
use App\Models\CreditPayment;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class CreditsQueryService
{
    private function getBaseQuery(): Builder
    {
        return Credit::query()
            ->where(function ($q) {
                $q->whereNull('credit_type')
                    ->orWhere('credit_type', '!=', 'cashea');
            })
            ->with('client');
    }

    private function applySorting(Builder $query, ?string $sortBy, string $orderBy): Builder
    {
        $direction = strtolower($orderBy) === 'asc' ? 'asc' : 'desc';

        if (empty($sortBy)) {
            return $query->orderBy('total_pending_amount', 'desc');
        }

        switch ($sortBy) {
            case 'client_full_name':
                return $query->leftJoin('clients', 'clients.id', '=', 'credits.client_id')
                    ->orderBy('clients.name', $direction);
            case 'total_pending_amount':
                return $query->orderBy('total_pending_amount', $direction);
            case 'credit_date':
                return $query->orderBy('credit_date', $direction);
            case 'status':
                return $query->orderBy('status', $direction);
        }

        return $query;
    }

    private function applySearch(Builder $query, ?string $search): Builder
    {
        if (empty($search)) {
            return $query;
        }

        return $query->whereHas('client', function ($q) use ($search) {
            $q->where('name', 'like', "%{$search}%")
                ->orWhere('last_name', 'like', "%{$search}%")
                ->orWhere('identification', 'like', "%{$search}%");
        });
    }

    public function getFilteredQuery(Request $request): Builder
    {
        $statusFilter = $request->input('status_filter', 'pending');
        $today = Carbon::today()->toDateString();

        $query = $this->getBaseQuery()
            ->select('credits.client_id')
            ->selectRaw('SUM(pending_amount) as total_pending_amount')
            ->selectRaw('GROUP_CONCAT(credits.id) as credit_ids')
            ->selectRaw('
                CASE 
                    WHEN SUM(credits.status = "Paid") = COUNT(*) THEN 2
                    WHEN SUM(pending_amount != credit_amount) = 0 THEN 0
                    ELSE 1
                END as status')
            ->selectRaw('MAX(credit_date) as credit_date')
            ->selectRaw('MIN(CASE WHEN pending_amount > 0 THEN credit_date ELSE NULL END) as oldest_credit_date')
            ->selectRaw('MIN(CASE WHEN pending_amount > 0 THEN next_due_date ELSE NULL END) as earliest_due_date')
            ->selectRaw('SUM(CASE WHEN pending_amount > 0 THEN 1 ELSE 0 END) as active_credits_count')
            ->groupBy('credits.client_id');

        if ($statusFilter === 'pending') {
            $query->havingRaw('SUM(pending_amount) > 0');
        } elseif ($statusFilter === 'overdue') {
            $query->whereNotNull('next_due_date')
                ->where('next_due_date', '<', $today)
                ->where('pending_amount', '>', 0);
        } elseif ($statusFilter === 'paid') {
            $query->havingRaw('SUM(pending_amount) <= 0');
        }

        $search = $request->input('search') ?: $request->input('client');
        $query = $this->applySearch($query, $search);

        $query = $this->applySorting(
            $query,
            $request->input('sortBy'),
            $request->input('orderBy', 'desc')
        );

        return $query;
    }

    /**
     * Calcula KPIs reales sobre toda la cartera de créditos (no solo la página actual).
     *
     * @return array<string, float|int>
     */
    public function getKpis(): array
    {
        $today = Carbon::today()->toDateString();

        $activeBase = Credit::query()
            ->where(function ($q) {
                $q->whereNull('credit_type')
                    ->orWhere('credit_type', '!=', 'cashea');
            })
            ->where('pending_amount', '>', 0);

        $totalPendingUsd = (float) (clone $activeBase)->sum('pending_amount');
        $totalDebtors = (int) (clone $activeBase)->distinct('client_id')->count('client_id');

        $overdueBase = (clone $activeBase)
            ->whereNotNull('next_due_date')
            ->where('next_due_date', '<', $today);

        $overdueAmountUsd = (float) (clone $overdueBase)->sum('pending_amount');
        $overdueDebtors = (int) (clone $overdueBase)->distinct('client_id')->count('client_id');

        return [
            'total_debtors' => $totalDebtors,
            'total_pending_usd' => round($totalPendingUsd, 2),
            'overdue_debtors' => $overdueDebtors,
            'overdue_amount_usd' => round($overdueAmountUsd, 2),
        ];
    }

    /**
     * Obtiene los detalles de los créditos seleccionados con sus órdenes y productos/platos.
     */
    public function getCreditDetails(array $creditIds): Collection
    {
        return Credit::with([
            'client',
            'order.details.product.laboratory',
            'order.details.dish',
        ])
            ->whereIn('id', $creditIds)
            ->orderByDesc('credit_date')
            ->get();
    }

    /**
     * Obtiene el historial de abonos activos para un cliente específico.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getClientPaymentHistory(int $clientId): array
    {
        $creditPayments = CreditPayment::with('seller')
            ->active()
            ->where('client_id', $clientId)
            ->orderBy('payment_date', 'desc')
            ->get();

        $payments = [];
        foreach ($creditPayments as $cp) {
            $methodPayments = $cp->method_Payment;
            if (is_string($methodPayments)) {
                $methodPayments = json_decode($methodPayments, true) ?: [];
            }
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
                    'id' => $cp->id,
                    'payment_date' => $paymentDate,
                    'amount' => $payment['amount'] ?? 0,
                    'method' => $payment['method'] ?? '',
                    'currency' => $payment['currency'] ?? 'USD',
                    'reference' => $payment['reference'] ?? '',
                    'seller' => $sellerName,
                ];
            }
        }

        return $payments;
    }

    /**
     * Lista paginada de abonos globales activos (compatible con SQLite y MySQL).
     */
    public function getPaymentsPaginated(Request $request): LengthAwarePaginator
    {
        $search = $request->input('client');
        $sortBy = $request->input('sort_by', 'date');
        $orderBy = in_array($request->input('order_by'), ['asc', 'desc'], true)
            ? $request->input('order_by')
            : 'desc';
        $itemsPerPage = (int) $request->input('items_per_page', 10);

        if (DB::getDriverName() === 'sqlite') {
            $creditPayments = CreditPayment::with(['client', 'seller'])
                ->active()
                ->get();
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
                    return substr((string) $item['date'], 0, 10) === $date;
                });
            }

            $paymentsCollection = $orderBy === 'desc'
                ? $paymentsCollection->sortByDesc($sortBy)
                : $paymentsCollection->sortBy($sortBy);

            $currentPage = LengthAwarePaginator::resolveCurrentPage();

            return new LengthAwarePaginator(
                $paymentsCollection->forPage($currentPage, $itemsPerPage)->values()->all(),
                $paymentsCollection->count(),
                $itemsPerPage,
                $currentPage,
                ['path' => LengthAwarePaginator::resolveCurrentPath()]
            );
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
                'cp.payment_date as date',
                DB::raw("CONCAT(clients.name, ' ', clients.last_name) as client"),
                'seller.username as seller',
            ])
            ->whereNull('cp.voided_at')
            ->where(function ($q) {
                $q->whereNull('cp.status')->orWhere('cp.status', '!=', 'voided');
            })
            ->where('payment.amount', '>', 0);

        if ($currency = $request->input('currency')) {
            $query->where('payment.currency', $currency);
        }

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('clients.name', 'LIKE', "%{$search}%")
                    ->orWhere('clients.last_name', 'LIKE', "%{$search}%")
                    ->orWhere('clients.identification', 'LIKE', "%{$search}%");
            });
        }

        if ($date = $request->input('date')) {
            $query->whereDate('cp.payment_date', $date);
        }

        switch ($sortBy) {
            case 'client':
                $query->orderBy('client', $orderBy);
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

        return $query->paginate($itemsPerPage);
    }
}
