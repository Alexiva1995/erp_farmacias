<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Data\CreateTransactionData;
use App\Models\Transaction;
use App\Models\CashClosing;
use App\TransactionType;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class TransactionRepository implements \App\Contracts\Transaction
{
    public function getAll(array $data): array
    {
        $perPage     = $data['per_page'] ?? 10; 
        $startDate   = $data['start_date'];
        $endDate     = $data['end_date'];
        $currency    = $data['currency'];
        $detailed    = $data['detailed'];
        $option      = $data['option'] ? substr($data['option'], 0, strpos($data['option'], '_')) : null;
        $currentPage = $data['page'] ?? 1;

        $datesQuery = Transaction::query()
            ->when($currency, fn($q, $cur) => $q->where('transactions.currency', $cur))
            ->when(
                $detailed && $option,
                fn($q) => ($currency === 'BS' && $option === 'TRANSFER')
                    ? $q->whereIn('transactions.type', ['CARD', 'TRANSFER'])
                    : $q->where('transactions.type', TransactionType::tryFrom($option)->value)
            )
            ->when(
                $startDate && $endDate,
                fn($q) => $q->whereBetween('transactions.transaction_date', [$startDate, $endDate])
            )
            ->select('transaction_date')
            ->distinct()
            ->orderByDesc('transaction_date');

        $paginatedDates = $datesQuery->paginate($perPage, ['*'], 'page', $currentPage);
        $activeDates    = $paginatedDates->pluck('transaction_date')->toArray();

        // Calcular el balance acumulado previo (Opening Balance)
        // Esto es necesario para calcular el balance línea a línea en el frontend
        $openingBalance = 0;
        if ($detailed && $option && $currency) {
            $openingBalance = Transaction::query()
                ->where('transactions.currency', $currency)
                ->when(
                    $currency === 'BS' && $option === 'TRANSFER',
                    fn($q) => $q->whereIn('transactions.type', ['CARD', 'TRANSFER']),
                    fn($q) => $q->where('transactions.type', TransactionType::tryFrom($option)->value)
                )
                ->where('transactions.transaction_date', '<', $startDate ?: now()->format('Y-m-d'))
                ->selectRaw("SUM(CASE WHEN movement_type = 'IN' THEN amount ELSE -amount END) as net")
                ->value('net') ?? 0;
        }

        if (empty($activeDates)) {
            return [
                'paginator' => $paginatedDates,
                'opening_balance' => $openingBalance,
                'previous_total_usd' => 0
            ];
        }

        $typesCondition = "t2.type = transactions.type";
        if ($currency === 'BS' && $option === 'TRANSFER') {
            $typesCondition = "t2.type IN ('CARD', 'TRANSFER')";
        }

        $results = Transaction::query()
            ->leftJoin('users', 'users.id', '=', 'transactions.user_id')
            ->leftJoin('expense_categories', 'expense_categories.id', '=', 'transactions.category_id')
            ->whereIn('transactions.transaction_date', $activeDates)
            ->when($currency, fn($q, $cur) => $q->where('transactions.currency', $cur))
            ->when(
                $detailed && $option,
                fn($q) => ($currency === 'BS' && $option === 'TRANSFER')
                    ? $q->whereIn('transactions.type', ['CARD', 'TRANSFER'])
                    : $q->where('transactions.type', TransactionType::tryFrom($option)->value)
            )
            ->select([
                'transactions.*',
                'users.username as user_name',
                'expense_categories.name as category_name',
                DB::raw("(
                    SELECT SUM(CASE WHEN t2.movement_type = 'IN' THEN t2.amount ELSE -t2.amount END)
                    FROM transactions t2
                    WHERE t2.currency = transactions.currency
                      AND {$typesCondition}
                      AND (t2.transaction_date < transactions.transaction_date 
                           OR (t2.transaction_date = transactions.transaction_date AND t2.id <= transactions.id))
                ) as balance")
            ])
            ->orderByDesc('transactions.transaction_date')
            ->orderByDesc('transactions.id')
            ->get();

        $results->transform(function ($transaction) {
            $enum = TransactionType::tryFrom($transaction->type);
            $transaction->type = $enum?->label() ?? $transaction->type;
            return $transaction;
        });

        $paginatedDates->setCollection($results);

        return [
            'paginator' => $paginatedDates,
            'opening_balance' => (float)$openingBalance,
            'previous_total_usd' => 0 
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


    public function getWallets(array $data): array
    {
        $startDate = $data['start_date'] ?? null;
        $endDate   = $data['end_date']   ?? null;

        $rows = Transaction::query()
            ->selectRaw("
                currency,
                CASE WHEN currency = 'BS' AND type IN ('CARD', 'TRANSFER') THEN 'TRANSFER' ELSE type END as type,
                SUM(CASE WHEN movement_type = 'IN'  THEN amount ELSE 0 END) as total_in,
                SUM(CASE WHEN movement_type = 'OUT' THEN amount ELSE 0 END) as total_out,
                SUM(CASE WHEN movement_type = 'IN'  THEN amount ELSE -amount END) as balance,
                COUNT(*) as transactions_count,
                AVG(COALESCE(exchange_rate,1)) as avg_rate
            ")
            ->when($startDate && $endDate, fn($q) => $q->whereBetween('transaction_date', [$startDate, $endDate]))
            ->groupBy('currency', DB::raw("CASE WHEN currency = 'BS' AND type IN ('CARD', 'TRANSFER') THEN 'TRANSFER' ELSE type END"))
            ->orderBy('currency')
            ->orderBy('type')
            ->get();

        $currencyOrder = ['USD', 'BS', 'COP'];
        $sections = [];
        $totalUsd = 0.0;

        foreach ($rows as $row) {
            $currency   = $row->currency;
            $method     = strtoupper($row->type);
            $balance    = (float) $row->balance;

            // Si el método es crédito en USD, mostramos la deuda global pendiente (Cuentas por Cobrar)
            if ($currency === 'USD' && $method === 'CREDIT') {
                $balance = (float) \App\Models\Credit::where('status', '!=', 'Paid')->sum('pending_amount');
            }

            $avgRate    = (float) ($row->avg_rate ?: 1);
            $balanceUsd = $currency === 'USD' ? $balance : round($balance / $avgRate, 2);

            if (!isset($sections[$currency])) {
                $sections[$currency] = ['currency' => $currency, 'section_total' => 0.0, 'wallets' => []];
            }

            $sections[$currency]['wallets'][] = [
                'key'                => $method . '_' . $currency,
                'currency'           => $currency,
                'method'             => $method,
                'balance'            => round($balance, 2),
                'total_in'           => round((float) $row->total_in,  2),
                'total_out'          => round((float) $row->total_out, 2),
                'transactions_count' => (int) $row->transactions_count,
                'balance_usd'        => $balanceUsd,
            ];

            $sections[$currency]['section_total'] += $balance;
            $totalUsd += $balanceUsd;
        }

        $orderedSections = [];
        foreach ($currencyOrder as $c) {
            if (isset($sections[$c])) {
                $sections[$c]['section_total'] = round($sections[$c]['section_total'], 2);
                $orderedSections[] = $sections[$c];
            }
        }

        return [
            'sections'  => $orderedSections,
            'total_usd' => round($totalUsd, 2),
        ];
    }

    public function getIncomeSummaryByMethod(array $data): array
    {
        $startDate = $data['start_date'] ?? now()->startOfMonth()->format('Y-m-d');
        $endDate   = $data['end_date']   ?? now()->endOfMonth()->format('Y-m-d');

        $currentRates = \App\Models\ExchangeRate::pluck('rate', 'currency_code')->toArray();
        $bsRate = (float) ($currentRates['BS'] ?? ($currentRates['EUR'] ?? 60.0));
        $copRate = (float) ($currentRates['COP'] ?? 4000.0);

        $closings = \App\Models\CashClosing::whereBetween('closing_date', [$startDate, $endDate])
            ->where('status', 'closed')
            ->get();

        $metrics = [
            'bs_mobile' => ['currency' => 'BS', 'type' => 'MOBILE'],
            'bs_transfer' => ['currency' => 'BS', 'type' => 'TRANSFER'],
            'bs_card_debito' => ['currency' => 'BS', 'type' => 'CARD'],
            'bs_card_credit' => ['currency' => 'BS', 'type' => 'CARD'],
            'bs_cash' => ['currency' => 'BS', 'type' => 'CASH'],
            'cop_cash' => ['currency' => 'COP', 'type' => 'CASH'],
            'cop_transfer' => ['currency' => 'COP', 'type' => 'TRANSFER'],
            'cop_spare' => ['currency' => 'COP', 'type' => 'CASH'],
            'usd_cash' => ['currency' => 'USD', 'type' => 'CASH'],
            'usd_binance' => ['currency' => 'USD', 'type' => 'BINANCE'],
            'usd_paypal' => ['currency' => 'USD', 'type' => 'PAYPAL'],
            'usd_credit' => ['currency' => 'USD', 'type' => 'CREDIT'],
            'usd_transfer' => ['currency' => 'USD', 'type' => 'TRANSFER'],
            'bs_cash_payment_credit' => ['currency' => 'BS', 'type' => 'CASH'],
            'bs_card_payment_credit' => ['currency' => 'BS', 'type' => 'CARD'],
            'bs_transfer_payment_credit' => ['currency' => 'BS', 'type' => 'TRANSFER'],
            'bs_mobile_payment_credit' => ['currency' => 'BS', 'type' => 'MOBILE'],
            'cop_cash_payment_credit' => ['currency' => 'COP', 'type' => 'CASH'],
            'cop_transfer_payment_credit' => ['currency' => 'COP', 'type' => 'TRANSFER'],
            'usd_transfer_payment_credit' => ['currency' => 'USD', 'type' => 'TRANSFER'],
            'usd_cash_payment_credit' => ['currency' => 'USD', 'type' => 'CASH'],
            'usd_paypal_payment_credit' => ['currency' => 'USD', 'type' => 'PAYPAL'],
            'usd_binance_payment_credit' => ['currency' => 'USD', 'type' => 'BINANCE'],
        ];

        $breakdown = [];

        foreach ($closings as $c) {
            foreach ($metrics as $field => $info) {
                $amount = (float) $c->$field;
                if ($amount > 0) {
                    $key = $info['type'] . '_' . $info['currency'];
                    if (!isset($breakdown[$key])) {
                        $enum = TransactionType::tryFrom($info['type']);
                        $breakdown[$key] = [
                            'method' => $enum?->label() ?? $info['type'],
                            'type' => $info['type'],
                            'currency' => $info['currency'],
                            'amount' => 0
                        ];
                    }
                    
                    $rate = 1.0;
                    if ($info['currency'] === 'BS') $rate = $bsRate;
                    elseif ($info['currency'] === 'COP') $rate = $copRate;
                    
                    if ($rate <= 0) $rate = 1.0;
                    $breakdown[$key]['amount'] += ($amount / $rate);
                }
            }
        }

        return collect($breakdown)
            ->map(function($item) {
                $item['amount'] = round($item['amount'], 2);
                return $item;
            })
            ->sortByDesc('amount')
            ->values()
            ->toArray();
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

    /**
     * Ajusta el saldo de un método/moneda creando una transacción de ajuste.
     */
    public function adjustBalance(array $data): void
    {
        $currency = $data['currency'];
        $type = $data['type'];
        $newBalance = (float)$data['new_balance'];

        // 1. Obtener balance actual para este método/moneda
        $wallets = $this->getWallets(['start_date' => null, 'end_date' => null]);
        $currentBalance = 0;

        foreach ($wallets['sections'] as $section) {
            if ($section['currency'] === $currency) {
                foreach ($section['wallets'] as $wallet) {
                    if ($wallet['method'] === $type) {
                        $currentBalance = (float)$wallet['balance'];
                        break 2;
                    }
                }
            }
        }

        $diff = $newBalance - $currentBalance;
        if (abs($diff) < 0.01) return;

        DB::transaction(function () use ($currency, $type, $diff) {
            // 2. Crear transacción de ajuste
            $transaction = new Transaction();
            $transaction->user_id = Auth::id();
            $transaction->description = "Ajuste manual de saldo (" . TransactionType::from($type)->label() . " $currency)";
            $transaction->currency = $currency;
            $transaction->type = $type;
            $transaction->amount = abs($diff);
            $transaction->movement_type = $diff > 0 ? 'IN' : 'OUT';
            $transaction->transaction_date = Carbon::now();
            $transaction->exchange_rate = 1.0;
            $transaction->save();

            // 3. Sincronizar con CashClosing si hay uno abierto para el vendedor
            $cashClosing = CashClosing::where('seller_id', Auth::id())
                ->where('status', CashClosing::OPEN)
                ->first();

            if ($cashClosing) {
                $field = $this->mapMethodToField($currency, $type);
                if ($field) {
                    $cashClosing->$field += $diff;
                    $cashClosing->recalculateTotals();
                    $cashClosing->save();
                }
            }
        });
    }

    private function mapMethodToField(string $currency, string $method): ?string
    {
        $map = [
            'USD' => [
                'CASH' => 'usd_cash',
                'BINANCE' => 'usd_binance',
                'PAYPAL' => 'usd_paypal',
                'CREDIT' => 'usd_credit',
                'TRANSFER' => 'usd_transfer',
            ],
            'BS' => [
                'CASH' => 'bs_cash',
                'MOBILE' => 'bs_mobile',
                'TRANSFER' => 'bs_transfer',
                'CARD' => 'bs_card_debito',
            ],
            'COP' => [
                'CASH' => 'cop_cash',
                'TRANSFER' => 'cop_transfer',
            ],
        ];

        return $map[$currency][$method] ?? null;
    }
}
