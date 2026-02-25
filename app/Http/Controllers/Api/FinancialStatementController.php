<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Expense;
use App\Models\ExchangeRate;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
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
                ->get(['total_usd', 'amount', 'currency']);

            $totalExpenses = $expenses->sum(function ($expense) use ($exchangeRates) {
                // Usar total_usd si está disponible, sino convertir amount
                if ($expense->total_usd > 0) {
                    return round((float) $expense->total_usd, 2);
                }
                return $this->convertToUsd($expense->amount ?? 0, $expense->currency ?? 'Bs', $exchangeRates);
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
        } catch (\Throwable $e) {
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
                ->get(['total_usd', 'amount', 'currency']);

            $totalExpenses = $expenses->sum(function ($expense) use ($exchangeRates) {
                // Usar total_usd si está disponible, sino convertir amount
                if ($expense->total_usd > 0) {
                    return round((float) $expense->total_usd, 2);
                }
                return $this->convertToUsd($expense->amount ?? 0, $expense->currency ?? 'Bs', $exchangeRates);
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
        } catch (\Throwable $e) {
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
            $startDate = $request->input('start_date', '2020-01-01');
            $endDate = $request->input('end_date', now()->format('Y-m-d'));
            $perPage = $request->input('per_page', 50);

            // Obtener tasas de cambio
            $exchangeRates = $this->getExchangeRates();

            // 1. Crear subconsulta para Órdenes
            $salesQuery = \Illuminate\Support\Facades\DB::table('orders')
                ->select([
                    'id',
                    'order_date as date',
                    \Illuminate\Support\Facades\DB::raw("'sale' as type"),
                    'total_amount as amount',
                    'currency',
                    'total_cost as costs',
                    'total_amount_usd as amount_usd',
                    'client_id as relation_id',
                    \Illuminate\Support\Facades\DB::raw("CONCAT('Venta #', id) as description")
                ])
                ->where('status', 'Completed')
                ->whereBetween('order_date', [$startDate, $endDate]);

            // 2. Crear subconsulta para Gastos
            $expensesQuery = \Illuminate\Support\Facades\DB::table('expenses')
                ->select([
                    'id',
                    'expense_date as date',
                    \Illuminate\Support\Facades\DB::raw("'expense' as type"),
                    'amount',
                    'currency',
                    \Illuminate\Support\Facades\DB::raw('0 as costs'),
                    'total_usd as amount_usd',
                    'category_id as relation_id',
                    'name as description'
                ])
                ->whereBetween('expense_date', [$startDate, $endDate])
                ->where(function ($query) {
                    $query->whereDoesntHave('category', function ($q) {
                        $q->where('name', 'Pagos de Facturas');
                    });
                })->orWhereNull('category_id'); // Fallback for raw DB query consistency

            // Nota: Para usar whereDoesntHave en DB::table, necesitamos una subconsulta o moverlo a Eloquent.
            // Vamos a refinar la consulta de gastos para que sea compatible con UNION a nivel de DB.
            $expensesQuery = \Illuminate\Support\Facades\DB::table('expenses')
                ->select([
                    'expenses.id',
                    'expenses.expense_date as date',
                    \Illuminate\Support\Facades\DB::raw("'expense' as type"),
                    'expenses.amount',
                    'expenses.currency',
                    \Illuminate\Support\Facades\DB::raw('0 as costs'),
                    'expenses.total_usd as amount_usd',
                    'expenses.category_id as relation_id',
                    'expenses.name as description'
                ])
                ->leftJoin('expense_categories', 'expenses.category_id', '=', 'expense_categories.id')
                ->whereBetween('expenses.expense_date', [$startDate, $endDate])
                ->where(function ($q) {
                    $q->where('expense_categories.name', '!=', 'Pagos de Facturas')
                        ->orWhereNull('expense_categories.name');
                });

            // 3. Unir y paginar
            $combinedQuery = $salesQuery->unionAll($expensesQuery)
                ->orderBy('date', 'desc');

            $paginatedData = $combinedQuery->paginate($perPage);

            // 4. Enriquecer los datos de la página actual con modelos y relaciones
            $items = collect($paginatedData->items());

            // Agrupar por tipo para cargar relaciones eficientemente
            $saleIds = $items->where('type', 'sale')->pluck('id');
            $expenseIds = $items->where('type', 'expense')->pluck('id');

            $orderModels = Order::with(['client:id,name'])->whereIn('id', $saleIds)->get()->keyBy('id');
            $expenseModels = Expense::with(['category:id,name'])->whereIn('id', $expenseIds)->get()->keyBy('id');

            // 5. Mapear resultados finales
            $processedItems = $items->map(function ($item) use ($orderModels, $expenseModels, $exchangeRates) {
                if ($item->type === 'sale') {
                    $order = $orderModels->get($item->id);
                    if (!$order)
                        return null;

                    $amountUsd = $item->amount_usd ?: $this->convertToUsd($item->amount, $item->currency, $exchangeRates);
                    $costUsd = $this->convertToUsd($item->costs, $item->currency, $exchangeRates);
                    $utilityUsd = $amountUsd - $costUsd;

                    return [
                        'id' => $item->id,
                        'type' => 'sale',
                        'date' => $item->date,
                        'description' => $item->description,
                        'client' => $order->client?->name ?? 'N/A',
                        'amount' => $amountUsd,
                        'costs' => $costUsd,
                        'profit' => $utilityUsd,
                        'original_amount' => $item->amount,
                        'original_currency' => $item->currency,
                        'monto_display' => sprintf('+ %s %.2f', $item->currency, $item->amount),
                        'costos_display' => sprintf('USD %.2f', $costUsd),
                        'utilidad_display' => sprintf('%s USD %.2f', ($utilityUsd >= 0 ? '+' : '-'), abs($utilityUsd)),
                    ];
                } else {
                    $expense = $expenseModels->get($item->id);
                    if (!$expense)
                        return null;

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
                        'monto_display' => sprintf('- USD %.2f', $amountUsd),
                        'costos_display' => 'USD 0.00',
                        'utilidad_display' => sprintf('- USD %.2f', $amountUsd),
                    ];
                }
            })->filter()->values();

            return response()->json([
                'success' => true,
                'data' => [
                    'transactions' => $processedItems,
                    'pagination' => [
                        'current_page' => $paginatedData->currentPage(),
                        'last_page' => $paginatedData->lastPage(),
                        'total' => $paginatedData->total(),
                        'per_page' => $paginatedData->perPage(),
                    ]
                ]
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener los detalles: ' . $e->getMessage() . ' en ' . $e->getFile() . ':' . $e->getLine()
            ], 500);
        }
    }
}
