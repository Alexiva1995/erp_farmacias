<?php

namespace App\Repository;

use App\Models\Transaction;
use App\TransactionType;

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
      ->leftJoin('categories', 'categories.id', '=', 'transactions.category_id')
      ->leftJoin('exchange_rates as r', 'r.id', '=', 'transactions.exchange_rate_id')
      ->when(
        $currency,
        fn($q, $cur) => $q->where('transactions.currency', $cur)
      )
      ->when(
        $detailed && $option,
        fn($q) => $q->where('transactions.type', TransactionType::tryFrom($option)->value)
      );

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
        'categories.name as category_name'
      ])
      ->orderBy('transactions.transaction_date', 'desc')
      ->orderBy('transactions.id', 'desc')
      ->paginate($perPage);

    $results->getCollection()->transform(function ($transaction) {
      $type = $transaction->type;
      $enum = TransactionType::tryFrom($type);
      $transaction->type = $enum->label();
      return $transaction;
    });

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

  private function calculateTotalUsdBefore(
    ?string $currency,
    ?string $endDate = null,
    ?array $beforeIds = null
  ): float {
    return (float) Transaction::query()
      ->leftJoin('exchange_rates as r', 'r.id', '=', 'transactions.exchange_rate_id')
      ->when($currency, fn($q) => $q->where('transactions.currency', $currency))
      ->when($endDate, fn($q) => $q->where('transactions.transaction_date', '<', $endDate))
      ->when($beforeIds, fn($q) => $q->whereIn('transactions.id', $beforeIds))
      ->selectRaw("
        ROUND(
          SUM(
            CASE
              WHEN transactions.currency = 'USD' THEN 
                CASE WHEN transactions.movement_type = 'IN' THEN transactions.amount ELSE -transactions.amount END
              WHEN transactions.currency = 'COP' THEN 
                CASE WHEN transactions.movement_type = 'IN' THEN transactions.amount ELSE -transactions.amount END * COALESCE(r.rate, 1)
              ELSE 
                CASE WHEN transactions.movement_type = 'IN' THEN transactions.amount ELSE -transactions.amount END / NULLIF(COALESCE(r.rate, 1), 0)
            END
          ), 2
        ) as total_usd
      ")
      ->value('total_usd') ?? 0.00;
  }

  private function calculateTotalUsdBetween(
    ?string $currency,
    string $startDate,
    string $endDate
  ): float {
    return (float) Transaction::query()
      ->leftJoin('exchange_rates as r', 'r.id', '=', 'transactions.exchange_rate_id')
      ->when($currency, fn($q) => $q->where('transactions.currency', $currency))
      ->whereBetween('transactions.transaction_date', [$startDate, $endDate])
      ->selectRaw("
        ROUND(
          SUM(
            CASE
              WHEN transactions.currency = 'USD' THEN 
                CASE WHEN transactions.movement_type = 'IN' THEN transactions.amount ELSE -transactions.amount END
              WHEN transactions.currency = 'COP' THEN 
                CASE WHEN transactions.movement_type = 'IN' THEN transactions.amount ELSE -transactions.amount END * COALESCE(r.rate, 1)
              ELSE 
                CASE WHEN transactions.movement_type = 'IN' THEN transactions.amount ELSE -transactions.amount END / NULLIF(COALESCE(r.rate, 1), 0)
            END
          ), 2
        ) as total_usd
      ")
      ->value('total_usd') ?? 0.00;
  }
}
