<?php

namespace App\Repository;

use App\Data\CreateTransactionData;
use App\Models\Transaction;
use App\TransactionType;
use Illuminate\Support\Facades\DB;

class TransactionRepository
{
public function getAll(array $data): array
{
    $perPage = $data['per_page'];
    $startDate = $data['start_date'];
    $endDate = $data['end_date'];
    $currency = $data['currency'];
    $detailed = $data['detailed'];
    $option = substr($data['option'], 0, strpos($data['option'], '_'));
    $currentPage = $data['page'] ?? 1;

    $baseQuery = Transaction::query()
        ->leftJoin('users', 'users.id', '=', 'transactions.user_id')
        ->leftJoin('expense_categories', 'expense_categories.id', '=', 'transactions.category_id')
        ->when(
            $currency,
            fn($q, $cur) => $q->where('transactions.currency', $cur)
        )
        ->when(
            $detailed && $option,
            fn($q) => $q->where('transactions.type', TransactionType::tryFrom($option)->value)
        )
        ->orderByDesc('transaction_date')
        ->orderByDesc('id');

    $previousTotal = 0.00;

    if ($startDate) {
        $previousTotal = $this->calculateTotalUsdBefore($currency, $startDate);
    } elseif ($currentPage > 1) {
        $previousIds = (clone $baseQuery)
            ->orderBy('transactions.transaction_date', 'desc')
            ->orderBy('transactions.id', 'desc')
            ->limit(($currentPage - 1) * $perPage)
            ->pluck('transactions.id');

        if ($previousIds->isNotEmpty()) {
            $previousTotal = $this->calculateTotalUsdBefore($currency, null, $previousIds->toArray());
        }
    }

    $results = (clone $baseQuery)
        ->when(
            $startDate && $endDate,
            fn($q) => $q->whereBetween('transactions.transaction_date', [$startDate, $endDate])
        )
        ->select([
            'transactions.*',
            'users.username as user_name',
            'expense_categories.name as category_name',
        ])
        ->orderByDesc('transactions.transaction_date')
        ->orderByDesc('transactions.id')
        ->paginate($perPage);

    $openingBalances = ['USD' => 0, 'COP' => 0, 'BS' => 0];

    if ($startDate) {
        $openingBalances = $this->calculateOpeningBalances($currency, $startDate);
    } elseif ($currentPage > 1) {
        $currentPageFirstId = $results->getCollection()->first()?->id;

        if ($currentPageFirstId) {
            $openingBalances = $this->calculateOpeningBalances($currency, null, [$currentPageFirstId]);
        }
    }

    // ========== CORRECCIÓN: CALCULAR BALANCE EN ORDEN CRONOLÓGICO ==========
    
    // 1. Obtener todas las transacciones en orden CRONOLÓGICO (ASCENDENTE) para calcular balance
    $allTransactionsQuery = Transaction::query()
        ->when(
            $currency,
            fn($q, $cur) => $q->where('transactions.currency', $cur)
        )
        ->when(
            $detailed && $option,
            fn($q) => $q->where('transactions.type', TransactionType::tryFrom($option)->value)
        );

    if ($startDate && $endDate) {
        $allTransactionsQuery->whereBetween('transactions.transaction_date', [$startDate, $endDate]);
    }

    $allTransactions = $allTransactionsQuery
        ->orderBy('transactions.transaction_date', 'asc')
        ->orderBy('transactions.id', 'asc')
        ->get(['id', 'currency', 'amount', 'movement_type']);

    // 2. Calcular balances por moneda en orden cronológico
    $balancesByTransactionId = [];
    $runningBalances = ['USD' => 0, 'COP' => 0, 'BS' => 0];
    
    // Aplicar balances iniciales (openingBalances)
    $runningBalances['USD'] = $openingBalances['USD'];
    $runningBalances['COP'] = $openingBalances['COP'];
    $runningBalances['BS'] = $openingBalances['BS'];

    foreach ($allTransactions as $transaction) {
        $currency = $transaction->currency;
        
        if ($transaction->movement_type === 'IN') {
            $runningBalances[$currency] += (float) $transaction->amount;
        } else {
            $runningBalances[$currency] -= (float) $transaction->amount;
        }
        
        $balancesByTransactionId[$transaction->id] = $runningBalances[$currency];
    }

    // 3. Asignar balances a las transacciones paginadas
    $results->getCollection()->transform(function ($transaction) use ($balancesByTransactionId) {
        // Asignar el balance calculado
        $transaction->balance = $balancesByTransactionId[$transaction->id] ?? 0;
        
        // Convertir tipo a etiqueta
        $enum = TransactionType::tryFrom($transaction->type);
        $transaction->type = $enum?->label() ?? $transaction->type;

        return $transaction;
    });
    // ========== FIN DE CORRECCIÓN ==========

    $results->appends($data)->withPath(request()->url());

    return [
        'paginator' => $results,
        'previous_total_usd' => $previousTotal
    ];
}

    public function getByType(array $data): array
    {
        $start_date = $data['start_date'] ?? now()->startOfMonth()->format('Y-m-d');
        $end_date = $data['end_date'] ?? now()->endOfMonth()->format('Y-m-d');
        $currency = $data['currency'];

        $totalValueInUsd = $this->calculateTotalUsdBetween($currency, $start_date, $end_date);

        $totalsByCurrency = Transaction::query()
            ->selectRaw('currency, SUM(CASE WHEN movement_type = "in" THEN amount ELSE -amount END) as total')
            ->when(!empty($currency), function ($query) use ($currency) {
                $query->where('currency', $currency);
            })
            ->whereBetween('transaction_date', [$start_date, $end_date])
            ->groupBy('currency')
            ->pluck('total', 'currency');

        $query = Transaction::query()
            ->selectRaw('currency, transaction_date, SUM(CASE WHEN movement_type = "in" THEN amount ELSE -amount END) as total_amount')
            ->when(!empty($currency), function ($query) use ($currency) {
                $query->where('currency', $currency);
            })
            ->whereBetween('transaction_date', [$start_date, $end_date])
            ->groupBy('transaction_date', 'currency')
            ->orderBy('transaction_date')
            ->get();

        $days = range(1, (int) substr($end_date, 8, 2));
        $skeleton = collect(['COP', 'BS', 'USD'])
            ->mapWithKeys(
                fn($c) => [$c => array_fill(0, count($days), 0)]
            )
            ->toArray();

        $query->groupBy('currency')->each(
            function ($group, $curr) use (&$skeleton, $days) {
                foreach ($group as $row) {
                    $day = (int) substr($row->transaction_date, 8, 2);
                    $index = array_search($day, $days);
                    if ($index !== false) {
                        $skeleton[$curr][$index] = (float) $row->total_amount;
                    }
                }
            }
        );

        foreach (['COP', 'BS', 'USD'] as $c) {
            $skeleton['total_' . strtolower($c)] = (float) $totalsByCurrency->get($c, 0);
        }
        $skeleton['total_value'] = (float) $totalValueInUsd;

        return $skeleton;
    }

    private function calculateTotalUsdBefore(?string $currency, ?string $endDate = null, ?array $beforeIds = null): float
    {
        return (float) Transaction::query()
            ->when($currency, fn($q) => $q->where('transactions.currency', $currency))
            ->when($endDate, fn($q) => $q->where('transactions.transaction_date', '<', $endDate))
            ->when($beforeIds, fn($q) => $q->whereIn('transactions.id', $beforeIds))
            ->selectRaw("
                ROUND(
                    SUM(
                        CASE
                            WHEN transactions.currency = 'USD' THEN 
                                CASE WHEN transactions.movement_type = 'IN' THEN transactions.amount ELSE -transactions.amount END
                            ELSE 
                                CASE WHEN transactions.movement_type = 'IN' THEN transactions.amount ELSE -transactions.amount END / NULLIF(COALESCE(transactions.exchange_rate, 1), 0)
                        END
                    ), 2
                ) as total_usd
            ")
            ->value('total_usd') ?? 0.00;
    }

    private function calculateTotalUsdBetween(?string $currency, string $startDate, string $endDate): float
    {
        return (float) Transaction::query()
            ->when($currency, fn($q) => $q->where('transactions.currency', $currency))
            ->whereBetween('transactions.transaction_date', [$startDate, $endDate])
            ->selectRaw("
                ROUND(
                    SUM(
                        CASE
                            WHEN transactions.currency = 'USD' THEN 
                                CASE WHEN transactions.movement_type = 'IN' THEN transactions.amount ELSE -transactions.amount END
                            ELSE 
                                CASE WHEN transactions.movement_type = 'IN' THEN transactions.amount ELSE -transactions.amount END / NULLIF(COALESCE(transactions.exchange_rate, 1), 0)
                        END
                    ), 2
                ) as total_usd
            ")
            ->value('total_usd') ?? 0.00;
    }

    private function calculateOpeningBalances(string $currency = null, string $beforeDate = null, array $beforeIds = []): array
    {
        $query = Transaction::query();

        if ($beforeDate) {
            $query->where('transaction_date', '<', $beforeDate);
        } elseif (!empty($beforeIds)) {
            $query->where('id', '<', min($beforeIds));
        } else {
            return ['USD' => 0, 'COP' => 0, 'BS' => 0];
        }

        if ($currency) {
            $query->where('currency', $currency);
        }

        $results = $query
            ->selectRaw("
                SUM(CASE WHEN movement_type = 'IN' THEN amount ELSE -amount END) as net_amount,
                currency
            ")
            ->groupBy('currency')
            ->pluck('net_amount', 'currency');

        return [
            'USD' => $results->get('USD', 0.0),
            'COP' => $results->get('COP', 0.0),
            'BS' => $results->get('BS', 0.0),
        ];
    }

    public function create(CreateTransactionData $data): ?Transaction
    {
        $record = new Transaction();
        $record->user_id = $data->user_id;
        $record->category_id = $data->category_id;
        $record->description = $data->description;
        $record->currency = $data->currency;
        $record->amount = $data->amount;
        $record->movement_type = $data->movement_type;
        $record->transaction_date = $data->transaction_date;
        $record->exchange_rate = $data->exchange_rate ?? 1.0000;
        $record->save();
        return $record;
    }
}
