<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\OrderDetail;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\ExchangeRate;
use App\Helpers\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class FinancialStatementController extends Controller
{
    /**
     * Obtener tasas de cambio desde la base de datos
     */
    private function getExchangeRates(): array
    {
        $exchangeRates = ExchangeRate::all()->pluck('rate', 'currency_code')->toArray();

        // Asegurar que USD tenga una tasa de 1 para conversiones directas
        $exchangeRates['USD'] = 1.00;

        // Normalizar 'BS' a 'Bs' si es necesario
        if (isset($exchangeRates['BS'])) {
            $exchangeRates['Bs'] = $exchangeRates['BS'];
            unset($exchangeRates['BS']);
        }

        return $exchangeRates;
    }

    /**
     * Convertir monto a USD usando las tasas de cambio
     */
    private function convertToUsd($amount, $currencyCode, $exchangeRates): float
    {
        // Si ya es USD, no hay conversión necesaria
        if (strtoupper($currencyCode) === 'USD') {
            return round((float) $amount, 2);
        }

        // Normalizar el código de moneda para buscar la tasa
        $normalizedCurrencyCode = strtoupper($currencyCode);
        if ($normalizedCurrencyCode === 'BS') {
            $normalizedCurrencyCode = 'Bs';
        }

        // Buscar la tasa de cambio
        if (isset($exchangeRates[$normalizedCurrencyCode]) && (float) $exchangeRates[$normalizedCurrencyCode] > 0) {
            return round((float) $amount / (float) $exchangeRates[$normalizedCurrencyCode], 2);
        }

        // Si la tasa no se encuentra o es cero, registrar una advertencia y devolver 0
        Log::warning("Tasa de cambio no encontrada o cero para la moneda: {$currencyCode}. Monto original: {$amount}");
        return 0.00;
    }

    /**
     * Obtener el estado de resultados completo
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $startDate = $request->input('start_date');
            $endDate = $request->input('end_date');

            // Si no se proporcionan fechas, usar desde el principio de los tiempos
            if (!$startDate) {
                $startDate = '2020-01-01';
            }
            if (!$endDate) {
                $endDate = now()->format('Y-m-d');
            }

            // Obtener tasas de cambio
            $exchangeRates = $this->getExchangeRates();

            // Calcular ingresos con conversión a USD
            $orders = Order::where('status', 'Completed')
                ->whereBetween('order_date', [$startDate, $endDate])
                ->get(['total_amount', 'currency']);

            $totalIncome = $orders->sum(function ($order) use ($exchangeRates) {
                return $this->convertToUsd($order->total_amount, $order->currency, $exchangeRates);
            });

            // Calcular costos con conversión a USD
            $ordersWithCosts = Order::where('status', 'Completed')
                ->whereBetween('order_date', [$startDate, $endDate])
                ->get(['total_cost', 'currency']);

            $totalCosts = $ordersWithCosts->sum(function ($order) use ($exchangeRates) {
                return $this->convertToUsd($order->total_cost ?? 0, $order->currency, $exchangeRates);
            });

            // Calcular gastos con conversión a USD
            $expenses = Expense::whereBetween('expense_date', [$startDate, $endDate])
                ->whereDoesntHave('category', function ($q) {
                    $q->where('name', 'Pagos de Facturas');
                })
                ->get(['amount_usd', 'amount_bs']);

            $totalExpenses = $expenses->sum(function ($expense) use ($exchangeRates) {
                // Usar amount_usd si está disponible, sino convertir amount_bs
                if ($expense->amount_usd) {
                    return round((float) $expense->amount_usd, 2);
                }
                return $this->convertToUsd($expense->amount_bs ?? 0, 'Bs', $exchangeRates);
            });

            // Calcular utilidad neta
            $netProfit = $totalIncome - $totalCosts - $totalExpenses;

            return response()->json([
                'success' => true,
                'data' => [
                    'income' => $totalIncome,
                    'costs' => $totalCosts,
                    'expenses' => $totalExpenses,
                    'net_profit' => $netProfit,
                    'date_range' => [
                        'start' => $startDate,
                        'end' => $endDate
                    ]
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener el estado de resultados: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Obtener el resumen del estado de resultados (4 cuadritos)
     */
    public function getSummary(Request $request): JsonResponse
    {
        try {
            $startDate = $request->input('start_date');
            $endDate = $request->input('end_date');
            // Si no se proporcionan fechas, usar desde el principio de los tiempos
            if (!$startDate) {
                $startDate = '2020-01-01';
            }
            if (!$endDate) {
                $endDate = now()->format('Y-m-d');
            }

            // Obtener tasas de cambio
            $exchangeRates = $this->getExchangeRates();

            // Calcular ingresos con conversión a USD
            $orders = Order::where('status', 'Completed')
                ->whereBetween('order_date', [$startDate, $endDate])
                ->get(['total_amount', 'currency']);

            $totalIncome = $orders->sum(function ($order) use ($exchangeRates) {
                return $this->convertToUsd($order->total_amount, $order->currency, $exchangeRates);
            });

            // Calcular costos con conversión a USD
            $ordersWithCosts = Order::where('status', 'Completed')
                ->whereBetween('order_date', [$startDate, $endDate])
                ->get(['total_cost', 'currency']);

            $totalCosts = $ordersWithCosts->sum(function ($order) use ($exchangeRates) {
                return $this->convertToUsd($order->total_cost ?? 0, $order->currency, $exchangeRates);
            });

            // Calcular gastos con conversión a USD
            $expenses = Expense::whereBetween('expense_date', [$startDate, $endDate])
                ->whereDoesntHave('category', function ($q) {
                    $q->where('name', 'Pagos de Facturas');
                })
                ->get(['amount_usd', 'amount_bs']);

            $totalExpenses = $expenses->sum(function ($expense) use ($exchangeRates) {
                // Usar amount_usd si está disponible, sino convertir amount_bs
                if ($expense->amount_usd) {
                    return round((float) $expense->amount_usd, 2);
                }
                return $this->convertToUsd($expense->amount_bs ?? 0, 'Bs', $exchangeRates);
            });

            // Calcular utilidad neta
            $netProfit = $totalIncome - $totalCosts - $totalExpenses;

            return response()->json([
                'success' => true,
                'data' => [
                    'income' => [
                        'label' => 'Ingresos Totales',
                        'amount' => $totalIncome,
                        'currency' => 'USD',
                        'icon' => 'mdi-currency-usd',
                        'color' => 'success'
                    ],
                    'costs' => [
                        'label' => 'Costos Totales',
                        'amount' => $totalCosts,
                        'currency' => 'USD',
                        'icon' => 'mdi-package-variant',
                        'color' => 'warning'
                    ],
                    'expenses' => [
                        'label' => 'Gastos Operativos',
                        'amount' => $totalExpenses,
                        'currency' => 'USD',
                        'icon' => 'mdi-chart-line',
                        'color' => 'error'
                    ],
                    'net_profit' => [
                        'label' => 'Utilidad Neta',
                        'amount' => $netProfit,
                        'currency' => 'USD',
                        'icon' => 'mdi-trending-up',
                        'color' => $netProfit >= 0 ? 'success' : 'error'
                    ],
                    'date_range' => [
                        'start' => $startDate,
                        'end' => $endDate,
                        'start_formatted' => Carbon::parse($startDate)->format('d/m/Y'),
                        'end_formatted' => Carbon::parse($endDate)->format('d/m/Y')
                    ]
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener el resumen: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Obtener los detalles del estado de resultados (tabla)
     */
    public function getDetails(Request $request): JsonResponse
    {
        try {
            $startDate = $request->input('start_date');
            $endDate = $request->input('end_date');

            // Si no se proporcionan fechas, usar desde el principio de los tiempos
            if (!$startDate) {
                $startDate = '2020-01-01';
            }
            if (!$endDate) {
                $endDate = now()->format('Y-m-d');
            }

            // Obtener tasas de cambio
            $exchangeRates = $this->getExchangeRates();

            // Obtener órdenes completadas
            $orders = Order::with(['client'])
                ->where('status', 'Completed')
                ->whereBetween('order_date', [$startDate, $endDate])
                ->orderBy('order_date', 'desc')
                ->get();

            // Obtener gastos
            $expenses = Expense::with(['category'])
                ->whereBetween('expense_date', [$startDate, $endDate])
                ->whereDoesntHave('category', function ($q) {
                    $q->where('name', 'Pagos de Facturas');
                })
                ->orderBy('expense_date', 'desc')
                ->get();

            // Procesar órdenes
            $processedOrders = $orders->map(function ($order) use ($exchangeRates) {
                $convertedAmountUsd = $this->convertToUsd($order->total_amount, $order->currency, $exchangeRates);
                $convertedCostUsd = $this->convertToUsd($order->total_cost ?? 0, $order->currency, $exchangeRates);
                $convertedUtilityUsd = $convertedAmountUsd - $convertedCostUsd;

                return [
                    'id' => $order->id,
                    'type' => 'sale',
                    'date' => $order->order_date,
                    'description' => 'Venta #' . $order->id,
                    'client' => $order->client->name ?? 'N/A',
                    'amount' => $order->total_amount_usd ?? $order->total_amount,
                    'costs' => $convertedCostUsd,
                    'profit' => $convertedUtilityUsd,
                    // Campos adicionales para el frontend
                    'original_amount' => $order->total_amount,
                    'original_currency' => $order->currency,
                    'monto_display' => sprintf('%s %s %.2f', '+', $order->currency, $order->total_amount),
                    'costos_display' => sprintf('USD %.2f', $convertedCostUsd),
                    'utilidad_display' => sprintf('%s USD %.2f', ($convertedUtilityUsd >= 0 ? '+' : '-'), abs($convertedUtilityUsd)),
                ];
            });

            // Procesar gastos
            $processedExpenses = $expenses->map(function ($expense) use ($exchangeRates) {
                $amountUsd = $expense->amount_usd ?: $this->convertToUsd($expense->amount_bs ?? 0, 'Bs', $exchangeRates);

                return [
                    'id' => $expense->id,
                    'type' => 'expense',
                    'date' => $expense->expense_date,
                    'description' => $expense->name,
                    'category' => $expense->category->name ?? 'Sin categoría',
                    'amount' => $amountUsd,
                    'costs' => 0,
                    'profit' => -$amountUsd,
                    // Campos adicionales para el frontend
                    'original_amount' => $expense->amount_usd ?: $expense->amount_bs,
                    'original_currency' => $expense->amount_usd ? 'USD' : 'Bs',
                    'monto_display' => sprintf('%s USD %.2f', '-', $amountUsd),
                    'costos_display' => 'USD 0.00',
                    'utilidad_display' => sprintf('-USD %.2f', $amountUsd),
                ];
            });

            // Combinar y ordenar por fecha
            $allTransactions = $processedOrders->concat($processedExpenses)
                ->sortByDesc('date')
                ->values();

            return response()->json([
                'success' => true,
                'data' => [
                    'sales' => $processedOrders,
                    'expenses' => $processedExpenses,
                    'all_transactions' => $allTransactions
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener los detalles: ' . $e->getMessage()
            ], 500);
        }
    }
}
