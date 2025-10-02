<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\OrderDetail;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Helpers\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class FinancialStatementController extends Controller
{
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

            // Calcular ingresos
            $totalIncome = Order::where('status', 'Completed')
                ->whereBetween('order_date', [$startDate, $endDate])
                ->sum(DB::raw('COALESCE(total_amount_usd, total_amount)'));

            // Calcular costos
            $totalCosts = OrderDetail::join('orders', 'orders.id', '=', 'order_details.order_id')
                ->where('orders.status', 'Completed')
                ->whereBetween('orders.order_date', [$startDate, $endDate])
                ->sum(DB::raw('order_details.unit_cost * order_details.quantity'));

            // Calcular gastos
            $totalExpenses = Expense::whereBetween('expense_date', [$startDate, $endDate])
                ->sum('amount_usd');

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

            // Calcular ingresos
            $totalIncome = Order::where('status', 'Completed')
                ->whereBetween('order_date', [$startDate, $endDate])
                ->sum(DB::raw('COALESCE(total_amount_usd, total_amount)'));

            // Calcular costos
            $totalCosts = OrderDetail::join('orders', 'orders.id', '=', 'order_details.order_id')
                ->where('orders.status', 'Completed')
                ->whereBetween('orders.order_date', [$startDate, $endDate])
                ->sum(DB::raw('order_details.unit_cost * order_details.quantity'));

            // Calcular gastos
            $totalExpenses = Expense::whereBetween('expense_date', [$startDate, $endDate])
                ->sum('amount_usd');

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

            // Obtener ventas
            $sales = Order::with(['client', 'seller'])
                ->where('status', 'Completed')
                ->whereBetween('order_date', [$startDate, $endDate])
                ->orderBy('order_date', 'desc')
                ->get()
                ->map(function ($order) {
                    $costs = OrderDetail::where('order_id', $order->id)
                        ->sum(DB::raw('unit_cost * quantity'));

                    return [
                        'id' => $order->id,
                        'type' => 'sale',
                        'date' => $order->order_date,
                        'description' => 'Venta #' . $order->id,
                        'client' => $order->client->name ?? 'N/A',
                        'amount' => $order->total_amount_usd ?? $order->total_amount,
                        'costs' => $costs,
                        'profit' => ($order->total_amount_usd ?? $order->total_amount) - $costs
                    ];
                });

            // Obtener gastos
            $expenses = Expense::with(['category'])
                ->whereBetween('expense_date', [$startDate, $endDate])
                ->orderBy('expense_date', 'desc')
                ->get()
                ->map(function ($expense) {
                    return [
                        'id' => $expense->id,
                        'type' => 'expense',
                        'date' => $expense->expense_date,
                        'description' => $expense->name,
                        'category' => $expense->category->name ?? 'Sin categoría',
                        'amount' => $expense->amount_usd,
                        'costs' => 0,
                        'profit' => -$expense->amount_usd
                    ];
                });

            // Combinar y ordenar por fecha
            $allTransactions = $sales->concat($expenses)
                ->sortByDesc('date')
                ->values();

            return response()->json([
                'success' => true,
                'data' => [
                    'sales' => $sales,
                    'expenses' => $expenses,
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
