<?php

namespace App\Services;

use App\Contracts\Repositories\FinancialStatementRepositoryInterface;
use App\Models\ExchangeRate;
use App\Models\GeneralSetting;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Log;

class FinancialStatementService
{
    public function __construct(
        protected FinancialStatementRepositoryInterface $repository
    ) {}

    public function getExchangeRates(): array
    {
        $exchangeRates = ExchangeRate::all()->pluck('rate', 'currency_code')->toArray();
        $exchangeRates['USD'] = 1.00;

        if (isset($exchangeRates['BS'])) {
            $exchangeRates['Bs'] = $exchangeRates['BS'];
            unset($exchangeRates['BS']);
        }

        return $exchangeRates;
    }

    public function convertToUsd(float|int|string|null $amount, ?string $currencyCode, array $exchangeRates): float
    {
        if (!$amount) return 0.00;
        if (strtoupper((string)$currencyCode) === 'USD') {
            return round((float) $amount, 2);
        }

        $normalizedCurrencyCode = strtoupper((string)$currencyCode);
        if ($normalizedCurrencyCode === 'BS') {
            $normalizedCurrencyCode = 'Bs';
        }

        if (isset($exchangeRates[$normalizedCurrencyCode]) && (float) $exchangeRates[$normalizedCurrencyCode] > 0) {
            return round((float) $amount / (float) $exchangeRates[$normalizedCurrencyCode], 2);
        }

        Log::warning("Tasa de cambio no encontrada o cero para la moneda: {$currencyCode}. Monto: {$amount}");
        return 0.00;
    }

    public function getDefaultStartDate(): string
    {
        $config = GeneralSetting::first();
        return $config->income_statement_reset_date ?? '2020-01-01';
    }

    public function calculateSummary(?string $startDate, ?string $endDate, ?string $search = null): array
    {
        $startDate = $startDate ?: $this->getDefaultStartDate();
        $endDate = $endDate ?: now()->format('Y-m-d');
        $exchangeRates = $this->getExchangeRates();

        $incomeByCurrency = $this->repository->getIncomeByCurrency($startDate, $endDate, $search);
        $totalIncome = 0.00;
        foreach ($incomeByCurrency as $currency => $total) {
            $totalIncome += $this->convertToUsd($total, $currency, $exchangeRates);
        }

        $costsByCurrency = $this->repository->getCostsByCurrency($startDate, $endDate, $search);
        $totalCosts = 0.00;
        foreach ($costsByCurrency as $currency => $total) {
            $totalCosts += $this->convertToUsd($total, $currency, $exchangeRates);
        }

        $expensesUsdSum = $this->repository->getExpensesUsdSum($startDate, $endDate, $search);
        $expensesByCurrency = $this->repository->getExpensesByCurrency($startDate, $endDate, $search);

        $totalExpenses = $expensesUsdSum;
        foreach ($expensesByCurrency as $currency => $total) {
            $totalExpenses += $this->convertToUsd($total, $currency ?: 'Bs', $exchangeRates);
        }

        $netProfit = $totalIncome - $totalCosts - $totalExpenses;

        return [
            'income' => $totalIncome,
            'costs' => $totalCosts,
            'expenses' => $totalExpenses,
            'net_profit' => $netProfit,
            'date_range' => [
                'start' => $startDate,
                'end' => $endDate,
            ],
        ];
    }

    public function getPaginatedDetails(?string $startDate, ?string $endDate, ?string $search, ?string $type, int $perPage = 50): array
    {
        $startDate = $startDate ?: $this->getDefaultStartDate();
        $endDate = $endDate ?: now()->format('Y-m-d');
        $exchangeRates = $this->getExchangeRates();

        $paginated = $this->repository->getPaginatedDetails($startDate, $endDate, $search, $type, $perPage);

        $processedItems = collect($paginated->items())->map(function ($item) use ($exchangeRates) {
            if ($item->type === 'sale') {
                $order = $item->model;
                if (!$order) return null;

                $amountUsd = $item->amount_usd ?: $this->convertToUsd($item->amount, $item->currency, $exchangeRates);
                $costUsd = round((float) ($item->costs ?? 0), 2);
                $profitUsd = $amountUsd - $costUsd;

                return [
                    'id' => $item->id,
                    'type' => 'sale',
                    'date' => $item->date,
                    'description' => $item->description,
                    'client' => $order->client?->name ?? 'N/A',
                    'amount' => $amountUsd,
                    'costs' => $costUsd,
                    'profit' => $profitUsd,
                    'original_amount' => $item->amount,
                    'original_currency' => $item->currency,
                ];
            } else {
                $expense = $item->model;
                if (!$expense) return null;

                $amountUsd = $item->amount_usd ?: $this->convertToUsd($item->amount, $item->currency, $exchangeRates);

                return [
                    'id' => $item->id,
                    'type' => 'expense',
                    'date' => $item->date,
                    'description' => $item->description,
                    'category' => $expense->category?->name ?? 'Sin categoría',
                    'amount' => $amountUsd,
                    'costs' => 0,
                    'profit' => -$amountUsd,
                    'original_amount' => $item->amount,
                    'original_currency' => $item->currency ?? 'Bs',
                ];
            }
        })->filter()->values();

        return [
            'transactions' => $processedItems,
            'pagination' => [
                'current_page' => $paginated->currentPage(),
                'last_page' => $paginated->lastPage(),
                'total' => $paginated->total(),
                'per_page' => $paginated->perPage(),
            ],
        ];
    }

    public function resetReportDate(): string
    {
        $config = GeneralSetting::first() ?? GeneralSetting::create([
            'fiscal_mode' => 'demo',
            'special_taxpayer_status' => 'desactivada',
        ]);

        $resetDate = now()->format('Y-m-d');
        $config->update(['income_statement_reset_date' => $resetDate]);

        return $resetDate;
    }
}
