<?php

declare(strict_types=1);

namespace App\Services\Wholesale;

use App\Models\Client;
use App\Models\Credit;
use App\Models\CreditPayment;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class WholesalePortfolioService
{
    /**
     * Obtiene el listado de clientes de cartera filtrado por rol y usuario asignado.
     */
    public function getPortfolioClients(array $filters, int $perPage = 25): LengthAwarePaginator
    {
        $query = Client::query()
            ->with(['salesRep:id,name', 'telemarketingUser:id,name', 'collectorUser:id,name', 'deliveryRoute:id,code,name']);

        if (!empty($filters['sales_rep_id'])) {
            $query->where('sales_rep_id', $filters['sales_rep_id']);
        }

        if (!empty($filters['telemarketing_user_id'])) {
            $query->where('telemarketing_user_id', $filters['telemarketing_user_id']);
        }

        if (!empty($filters['collector_user_id'])) {
            $query->where('collector_user_id', $filters['collector_user_id']);
        }

        if (!empty($filters['delivery_route_id'])) {
            $query->where('delivery_route_id', $filters['delivery_route_id']);
        }

        if (!empty($filters['search'])) {
            $search = trim($filters['search']);
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('identification', 'like', "%{$search}%")
                  ->orWhere('phone', 'like', "%{$search}%")
                  ->orWhere('sicm_code', 'like', "%{$search}%");
            });
        }

        if (isset($filters['has_debt']) && $filters['has_debt']) {
            $query->whereHas('credits', function ($q) {
                $q->where('status', 'Active')->where('pending_amount', '>', 0);
            });
        }

        if (isset($filters['is_overdue']) && $filters['is_overdue']) {
            $today = Carbon::today()->toDateString();
            $query->whereHas('credits', function ($q) use ($today) {
                $q->where('status', 'Active')
                  ->where('pending_amount', '>', 0)
                  ->where('next_due_date', '<', $today);
            });
        }

        return $query->orderBy('name')->paginate($perPage);
    }

    /**
     * Resumen de KPIs financieros de la cartera asignada a un usuario o general.
     */
    public function getPortfolioMetrics(?int $userId = null, ?string $roleType = null): array
    {
        $today = Carbon::today()->toDateString();

        $clientQuery = Client::query();
        $creditQuery = Credit::query()->where('status', 'Active')->where('pending_amount', '>', 0);

        if ($userId !== null && $roleType !== null) {
            match ($roleType) {
                'sales_rep' => (function () use ($clientQuery, $creditQuery, $userId) {
                    $clientQuery->where('sales_rep_id', $userId);
                    $creditQuery->where('sales_rep_id', $userId);
                })(),
                'telemarketing' => (function () use ($clientQuery, $userId) {
                    $clientQuery->where('telemarketing_user_id', $userId);
                })(),
                'collector' => (function () use ($clientQuery, $creditQuery, $userId) {
                    $clientQuery->where('collector_user_id', $userId);
                    $creditQuery->where('collector_user_id', $userId);
                })(),
                default => null,
            };
        }

        $totalClients = (clone $clientQuery)->count();
        $totalCreditLimit = (float) (clone $clientQuery)->sum('credit_limit_usd');
        $totalPendingDebt = (float) (clone $creditQuery)->sum('pending_amount');

        $totalOverdueDebt = (float) (clone $creditQuery)
            ->where('next_due_date', '<', $today)
            ->sum('pending_amount');

        $clientsWithOverdueDebt = (clone $clientQuery)
            ->whereHas('credits', function ($q) use ($today) {
                $q->where('status', 'Active')
                  ->where('pending_amount', '>', 0)
                  ->where('next_due_date', '<', $today);
            })
            ->count();

        $availableCredit = max(0, $totalCreditLimit - $totalPendingDebt);

        return [
            'total_clients' => $totalClients,
            'total_credit_limit_usd' => $totalCreditLimit,
            'total_pending_debt_usd' => $totalPendingDebt,
            'total_overdue_debt_usd' => $totalOverdueDebt,
            'available_credit_usd' => $availableCredit,
            'clients_with_overdue_debt' => $clientsWithOverdueDebt,
        ];
    }

    /**
     * Asignación o reasignación masiva de cartera a asesores, teleoperadores o cobradores.
     */
    public function assignPortfolio(array $clientIds, array $assignments): int
    {
        $payload = [];

        if (array_key_exists('sales_rep_id', $assignments)) {
            $payload['sales_rep_id'] = $assignments['sales_rep_id'];
        }
        if (array_key_exists('telemarketing_user_id', $assignments)) {
            $payload['telemarketing_user_id'] = $assignments['telemarketing_user_id'];
        }
        if (array_key_exists('collector_user_id', $assignments)) {
            $payload['collector_user_id'] = $assignments['collector_user_id'];
        }
        if (array_key_exists('delivery_route_id', $assignments)) {
            $payload['delivery_route_id'] = $assignments['delivery_route_id'];
        }

        if (empty($payload)) {
            return 0;
        }

        return DB::transaction(function () use ($clientIds, $payload) {
            $updated = Client::whereIn('id', $clientIds)->update($payload);

            // Sincronizar también en créditos activos si se reasigna asesor o cobrador
            $creditUpdates = [];
            if (isset($payload['sales_rep_id'])) {
                $creditUpdates['sales_rep_id'] = $payload['sales_rep_id'];
            }
            if (isset($payload['collector_user_id'])) {
                $creditUpdates['collector_user_id'] = $payload['collector_user_id'];
            }

            if (!empty($creditUpdates)) {
                Credit::whereIn('client_id', $clientIds)
                    ->where('status', 'Active')
                    ->update($creditUpdates);
            }

            return $updated;
        });
    }

    /**
     * Calcula las comisiones de cobranza de un Asesor Comercial sobre pagos conciliados en un período.
     */
    public function calculateSalesRepCommissions(int $salesRepId, string $startDate, string $endDate, float $commissionPct = 2.0): array
    {
        $payments = CreditPayment::where('status', 'approved')
            ->whereHas('credit', function ($q) use ($salesRepId) {
                $q->where('sales_rep_id', $salesRepId);
            })
            ->whereBetween('payment_date', [$startDate . ' 00:00:00', $endDate . ' 23:59:59'])
            ->with(['client:id,name,identification', 'credit:id,invoice_number,credit_amount'])
            ->get();

        $totalCollectedUSD = 0.0;
        $items = [];

        foreach ($payments as $payment) {
            $amountUSD = (float) ($payment->amount_usd_equivalent > 0 ? $payment->amount_usd_equivalent : $payment->amount);
            $commissionAmount = round($amountUSD * ($commissionPct / 100), 2);
            $totalCollectedUSD += $amountUSD;

            $items[] = [
                'payment_id' => $payment->id,
                'payment_date' => $payment->payment_date?->format('Y-m-d'),
                'client_name' => $payment->client?->name,
                'invoice_number' => $payment->credit?->invoice_number,
                'amount_collected_usd' => $amountUSD,
                'commission_earned_usd' => $commissionAmount,
            ];
        }

        $totalCommissionUSD = round($totalCollectedUSD * ($commissionPct / 100), 2);

        return [
            'sales_rep_id' => $salesRepId,
            'start_date' => $startDate,
            'end_date' => $endDate,
            'commission_percentage' => $commissionPct,
            'total_collected_usd' => $totalCollectedUSD,
            'total_commission_usd' => $totalCommissionUSD,
            'payments_count' => count($items),
            'details' => $items,
        ];
    }
}
