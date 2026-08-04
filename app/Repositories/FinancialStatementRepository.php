<?php

namespace App\Repositories;

use App\Contracts\Repositories\FinancialStatementRepositoryInterface;
use App\Models\Expense;
use App\Models\Order;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class FinancialStatementRepository implements FinancialStatementRepositoryInterface
{
    public function getIncomeByCurrency(?string $startDate, ?string $endDate, ?string $search = null): array
    {
        $query = Order::query()
            ->where('status', 'Completed')
            ->when($startDate && $endDate, fn ($q) => $q->whereBetween('order_date', [$startDate, $endDate]))
            ->when($search, fn ($q) => $q->where('id', 'like', "%{$search}%"));

        return $query->selectRaw('currency, SUM(total_amount) as total')
            ->groupBy('currency')
            ->pluck('total', 'currency')
            ->toArray();
    }

    public function getCostsByCurrency(?string $startDate, ?string $endDate, ?string $search = null): array
    {
        $query = Order::query()
            ->where('status', 'Completed')
            ->when($startDate && $endDate, fn ($q) => $q->whereBetween('order_date', [$startDate, $endDate]))
            ->when($search, fn ($q) => $q->where('id', 'like', "%{$search}%"));

        return $query->selectRaw('currency, SUM(COALESCE(total_cost, 0)) as total')
            ->groupBy('currency')
            ->pluck('total', 'currency')
            ->toArray();
    }

    public function getExpensesUsdSum(?string $startDate, ?string $endDate, ?string $search = null): float
    {
        return (float) Expense::query()
            ->when($startDate && $endDate, fn ($q) => $q->whereBetween('expense_date', [$startDate, $endDate]))
            ->whereDoesntHave('category', fn ($q) => $q->where('name', 'Pagos de Facturas'))
            ->where('total_usd', '>', 0)
            ->when($search, fn ($q) => $q->where('name', 'like', "%{$search}%"))
            ->sum('total_usd');
    }

    public function getExpensesByCurrency(?string $startDate, ?string $endDate, ?string $search = null): array
    {
        return Expense::query()
            ->when($startDate && $endDate, fn ($q) => $q->whereBetween('expense_date', [$startDate, $endDate]))
            ->whereDoesntHave('category', fn ($q) => $q->where('name', 'Pagos de Facturas'))
            ->where(function ($query) {
                $query->whereNull('total_usd')->orWhere('total_usd', 0);
            })
            ->when($search, fn ($q) => $q->where('name', 'like', "%{$search}%"))
            ->selectRaw('currency, SUM(COALESCE(amount, 0)) as total')
            ->groupBy('currency')
            ->pluck('total', 'currency')
            ->toArray();
    }

    public function getPaginatedDetails(?string $startDate, ?string $endDate, ?string $search, ?string $type, int $perPage = 50): LengthAwarePaginator
    {
        $salesQuery = DB::table('orders')
            ->select([
                'id',
                'order_date as date',
                DB::raw("'sale' as type"),
                'total_amount as amount',
                'currency',
                'total_cost as costs',
                'total_amount_usd as amount_usd',
                'client_id as relation_id',
                DB::raw("CONCAT('Venta #', id) as description")
            ])
            ->where('status', 'Completed')
            ->when($startDate && $endDate, fn ($q) => $q->whereBetween('order_date', [$startDate, $endDate]))
            ->when($search, fn ($q) => $q->where('id', 'like', "%{$search}%"));

        $expensesQuery = DB::table('expenses')
            ->select([
                'expenses.id',
                'expenses.expense_date as date',
                DB::raw("'expense' as type"),
                'expenses.amount',
                'expenses.currency',
                DB::raw('0 as costs'),
                'expenses.total_usd as amount_usd',
                'expenses.category_id as relation_id',
                'expenses.name as description'
            ])
            ->leftJoin('expense_categories', 'expenses.category_id', '=', 'expense_categories.id')
            ->when($startDate && $endDate, fn ($q) => $q->whereBetween('expenses.expense_date', [$startDate, $endDate]))
            ->where(function ($q) {
                $q->where('expense_categories.name', '!=', 'Pagos de Facturas')
                    ->orWhereNull('expense_categories.name');
            })
            ->when($search, fn ($q) => $q->where('expenses.name', 'like', "%{$search}%"));

        if ($type === 'sale') {
            $combinedQuery = $salesQuery;
        } elseif ($type === 'expense') {
            $combinedQuery = $expensesQuery;
        } else {
            $combinedQuery = $salesQuery->unionAll($expensesQuery);
        }

        $paginated = $combinedQuery->orderBy('date', 'desc')->paginate($perPage);

        // Pre-cargar relaciones eficientemente sin N+1
        $items = collect($paginated->items());
        $saleIds = $items->where('type', 'sale')->pluck('id');
        $expenseIds = $items->where('type', 'expense')->pluck('id');

        $orderModels = Order::with(['client:id,name'])->whereIn('id', $saleIds)->get()->keyBy('id');
        $expenseModels = Expense::with(['category:id,name'])->whereIn('id', $expenseIds)->get()->keyBy('id');

        $items->transform(function ($item) use ($orderModels, $expenseModels) {
            if ($item->type === 'sale') {
                $item->model = $orderModels->get($item->id);
            } else {
                $item->model = $expenseModels->get($item->id);
            }
            return $item;
        });

        return $paginated;
    }
}
