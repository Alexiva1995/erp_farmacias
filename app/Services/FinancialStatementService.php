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
        return now()->startOfMonth()->format('Y-m-d');
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
        $marginPercentage = $totalIncome > 0 ? round((($totalIncome - $totalCosts) / $totalIncome) * 100, 2) : 0.00;

        return [
            'income' => round($totalIncome, 2),
            'costs' => round($totalCosts, 2),
            'expenses' => round($totalExpenses, 2),
            'net_profit' => round($netProfit, 2),
            'margin_percentage' => $marginPercentage,
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
                $profitUsd = round($amountUsd - $costUsd, 2);
                $marginPercentage = $amountUsd > 0 ? round(($profitUsd / $amountUsd) * 100, 2) : 0.00;

                $voucherNumber = !empty($order->fiscalHistory?->invoice_number)
                    ? "FAC-{$order->fiscalHistory->invoice_number}"
                    : "TKT-" . str_pad((string) $order->id, 6, '0', STR_PAD_LEFT);

                // Obtener métodos de pago / canal
                $pmList = $order->payment_methods ?? [];
                $channels = [];
                if (is_array($pmList)) {
                    foreach ($pmList as $pm) {
                        if (isset($pm['method']) && !empty($pm['method'])) {
                            $channels[] = $pm['method'];
                        }
                    }
                }
                $channel = !empty($channels) ? implode(', ', array_unique($channels)) : ($order->currency ?? 'USD');

                return [
                    'id' => $item->id,
                    'type' => 'sale',
                    'voucher_number' => $voucherNumber,
                    'date' => $item->date,
                    'description' => $item->description,
                    'client' => $order->client?->name ?? 'Consumidor Final',
                    'channel' => $channel,
                    'amount' => $amountUsd,
                    'costs' => $costUsd,
                    'profit' => $profitUsd,
                    'margin_percentage' => $marginPercentage,
                    'original_amount' => $item->amount,
                    'original_currency' => $item->currency,
                ];
            } else {
                $expense = $item->model;
                if (!$expense) return null;

                $amountUsd = $item->amount_usd ?: $this->convertToUsd($item->amount, $item->currency, $exchangeRates);
                $voucherNumber = "EGR-" . str_pad((string) $expense->id, 6, '0', STR_PAD_LEFT);

                return [
                    'id' => $item->id,
                    'type' => 'expense',
                    'voucher_number' => $voucherNumber,
                    'date' => $item->date,
                    'description' => $item->description,
                    'client' => $expense->category?->name ?? 'Gasto General',
                    'channel' => $expense->count ?? 'Efectivo',
                    'amount' => $amountUsd,
                    'costs' => 0.00,
                    'profit' => -$amountUsd,
                    'margin_percentage' => -100.00,
                    'original_amount' => $item->amount,
                    'original_currency' => $item->currency ?? 'Bs',
                ];
            }
        })->filter()->values();

        // Totales de la página actual
        $pageTotalSales = $processedItems->where('type', 'sale')->sum('amount');
        $pageTotalCosts = $processedItems->where('type', 'sale')->sum('costs');
        $pageTotalExpenses = $processedItems->where('type', 'expense')->sum('amount');
        $pageTotalProfit = $pageTotalSales - $pageTotalCosts - $pageTotalExpenses;
        $pageMarginPercent = $pageTotalSales > 0 ? round((($pageTotalSales - $pageTotalCosts) / $pageTotalSales) * 100, 2) : 0.00;

        $pageTotals = [
            'amount' => round($pageTotalSales, 2),
            'costs' => round($pageTotalCosts, 2),
            'expenses' => round($pageTotalExpenses, 2),
            'profit' => round($pageTotalProfit, 2),
            'margin_percentage' => $pageMarginPercent,
        ];

        return [
            'transactions' => $processedItems,
            'page_totals' => $pageTotals,
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
