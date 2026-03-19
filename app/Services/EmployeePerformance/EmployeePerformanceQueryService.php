<?php

namespace App\Services\EmployeePerformance;

use App\Models\Employee;
use App\Models\Order;
use App\Models\Invoice;
use App\Models\ProductCount;
use App\Models\SaleCount;
use App\Models\InvoiceCount;
use App\Models\CleaningActivityExecution;
use App\Models\EmployeePerformanceSnapshot;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class EmployeePerformanceQueryService
{
    public function getEmployeesWithPerformance(int $month, int $year): Collection
    {
        // Calculate previous month for growth comparisons
        $date = Carbon::create($year, $month, 1);
        $prevMonthDate = $date->copy()->subMonth();
        $prevMonth = $prevMonthDate->month;
        $prevYear = $prevMonthDate->year;

        // 0. Only check snapshots for PAST months. Current month is ALWAYS live.
        $isCurrentMonth = ($month == now()->month && $year == now()->year);

        if (!$isCurrentMonth) {
            try {
                $snapshots = EmployeePerformanceSnapshot::with('employee')
                    ->where('month', $month)
                    ->where('year', $year)
                    ->get();

                if ($snapshots->isNotEmpty()) {
                    return $snapshots->map(function ($s) {
                        return [
                            'id' => $s->employee_id,
                            'name' => $s->name,
                            'last_name' => $s->last_name,
                            'identification' => $s->employee->identification ?? '',
                            'photo' => $s->employee->photo ?? '',
                            'sales' => (float)$s->sales,
                            'growth' => (float)$s->growth,
                            'expirations' => $s->expirations,
                            'inventory_counted' => $s->inventory_counted,
                            'inventory_errors' => $s->inventory_errors,
                            'premium_products' => $s->premium_products,
                            'cleaning_assigned' => $s->cleaning_assigned,
                            'cleaning_completed' => $s->cleaning_completed,
                            'strategy_sales' => $s->strategy_sales,
                            'invoice_items' => $s->invoice_items,
                            'invoice_headers' => $s->invoice_headers,
                            'invoice_archived' => $s->invoice_archived,
                            'scores' => [
                                'sales' => (float)$s->score_sales,
                                'growth' => (float)$s->score_growth,
                                'expiration' => (float)$s->score_expiration,
                                'inventory' => (float)$s->score_inventory,
                                'premium' => (float)$s->score_premium,
                                'invoice' => (float)$s->score_invoice,
                                'cleaning' => (float)$s->score_cleaning,
                                'strategy' => (float)$s->score_strategy,
                                'total' => (float)$s->total_score,
                            ],
                            'is_locked' => true
                        ];
                    });
                }
            } catch (\Exception $e) {
                // Table doesn't exist yet or other SQL error, fall back to LIVE
                \Log::warning("No se pudo consultar snapshots (posiblemente tabla inexistente): " . $e->getMessage());
            }
        }

        // 1. Calculate RAW metrics for all employees (Live)
        $employeesData = Employee::where('is_active', true)
            ->select(['id', 'name', 'last_name', 'identification', 'photo', 'user_id'])
            ->get()
            ->map(function ($employee) use ($month, $year, $prevMonth, $prevYear) {

                $metrics = [
                    'sales' => 0.0,
                    'growth' => 0.0,
                    'expirations' => 0,
                    'inventory_counted' => 0,
                    'inventory_errors' => 0,
                    'premium_products' => 0,
                    'cleaning_assigned' => 0,
                    'cleaning_completed' => 0,
                    'strategy_sales' => 0,
                    // Invoice Raw Metrics
                    'invoice_items' => 0,
                    'invoice_headers' => 0,
                    'invoice_archived' => 0,
                ];

                if ($employee->user_id) {
                    // Sales & Growth
                    $metrics['sales'] = $this->calculateSales($employee->user_id, $month, $year);
                    $metrics['growth'] = $this->calculateGrowth($employee->user_id, $metrics['sales'], $prevMonth, $prevYear);

                    // Order Dependent Metrics
                    $orderMetrics = $this->calculateOrderDependentMetrics($employee, $month, $year);
                    $metrics = array_merge($metrics, $orderMetrics);

                    // Inventory Cyclic
                    $inventoryMetrics = $this->calculateInventoryMetrics($employee->user_id, $month, $year);
                    $metrics = array_merge($metrics, $inventoryMetrics);

                    // Invoice Metrics (Raw)
                    $invoiceMetrics = $this->calculateInvoiceMetrics($employee->user_id, $month, $year);
                    $metrics = array_merge($metrics, $invoiceMetrics);
                }

                // Cleaning
                $cleaningMetrics = $this->calculateCleaningMetrics($employee->id, $month, $year);
                $metrics = array_merge($metrics, $cleaningMetrics);

                return [
                    'employee' => $employee,
                    'metrics' => $metrics
                ];
            });

        // 2. Find MAX values for dynamic scoring
        $maxSales = $employeesData->max('metrics.sales') ?: 1;
        $maxGrowth = $employeesData->max('metrics.growth');
        $maxExpirations = $employeesData->max('metrics.expirations') ?: 1;
        $maxInventoryCount = $employeesData->max('metrics.inventory_counted') ?: 1;
        $maxPremium = $employeesData->max('metrics.premium_products') ?: 1;
        $maxCleaningCompleted = $employeesData->max('metrics.cleaning_completed') ?: 1;
        $maxStrategy = $employeesData->max('metrics.strategy_sales') ?: 1;
        $maxInvoiceItems = $employeesData->max('metrics.invoice_items') ?: 1;
        $maxInvoiceHeaders = $employeesData->max('metrics.invoice_headers') ?: 1;
        $maxInvoiceArchived = $employeesData->max('metrics.invoice_archived') ?: 1;

        // 3. Normalize Scores and build Final Collection
        return $employeesData->map(function ($data) use (
            $maxSales, $maxGrowth, $maxExpirations, $maxInventoryCount, 
            $maxPremium, $maxCleaningCompleted, $maxStrategy,
            $maxInvoiceItems, $maxInvoiceHeaders, $maxInvoiceArchived
        ) {
            $employee = $data['employee'];
            $metrics = $data['metrics'];

            // Puntaje de Crecimiento (Growth) - Refactorizado para proporcionalidad y capping
            if ($maxGrowth > 0) {
                $growthScore = ($metrics['growth'] / $maxGrowth) * 15;
            } elseif ($maxGrowth < 0) {
                // Si todos decrecen, el que decrece menos (más cercano a 0) obtiene 15 puntos.
                // Usamos la relación inversa: maxGrowth / growth actual.
                $growthScore = ($metrics['growth'] != 0) ? ($maxGrowth / $metrics['growth']) * 15 : 0;
            } else {
                $growthScore = 0;
            }

            // Aplicar Capping de -15 a 15
            $scores = [
                'sales' => ($metrics['sales'] / $maxSales) * 25,
                'growth' => max(-15, min(15, $growthScore)),
                'expiration' => ($metrics['expirations'] / $maxExpirations) * 15,
                'inventory' => max(0, (($metrics['inventory_counted'] / $maxInventoryCount) * 10) - ($metrics['inventory_errors'] * 0.01)),
                'premium' => ($metrics['premium_products'] / $maxPremium) * 10,
                'invoice' => (($metrics['invoice_items'] / $maxInvoiceItems) * 5) + 
                             (($metrics['invoice_headers'] / $maxInvoiceHeaders) * 2.5) + 
                             (($metrics['invoice_archived'] / $maxInvoiceArchived) * 2.5),
                'cleaning' => ($metrics['cleaning_completed'] / $maxCleaningCompleted) * 5,
                'strategy' => ($metrics['strategy_sales'] / $maxStrategy) * 5,
            ];

            $scores['total'] = array_sum($scores);

            return [
                'id' => $employee->id,
                'name' => $employee->name,
                'last_name' => $employee->last_name,
                'identification' => $employee->identification,
                'photo' => $employee->photo,
                'is_locked' => false,
                'scores' => $scores,
                ...$metrics
            ];
        });
    }

    /**
     * Capture a snapshot of the current month's performance.
     */
    public function captureSnapshot(int $month, int $year): bool
    {
        return DB::transaction(function () use ($month, $year) {
            EmployeePerformanceSnapshot::where('month', $month)->where('year', $year)->delete();

            $liveData = $this->getEmployeesWithPerformance($month, $year);

            foreach ($liveData as $data) {
                EmployeePerformanceSnapshot::create([
                    'employee_id' => $data['id'],
                    'month' => $month,
                    'year' => $year,
                    'name' => $data['name'],
                    'last_name' => $data['last_name'],
                    'sales' => $data['sales'],
                    'growth' => $data['growth'],
                    'expirations' => $data['expirations'],
                    'inventory_counted' => $data['inventory_counted'],
                    'inventory_errors' => $data['inventory_errors'],
                    'premium_products' => $data['premium_products'],
                    'cleaning_assigned' => $data['cleaning_assigned'],
                    'cleaning_completed' => $data['cleaning_completed'],
                    'strategy_sales' => $data['strategy_sales'],
                    'invoice_items' => $data['invoice_items'],
                    'invoice_headers' => $data['invoice_headers'],
                    'invoice_archived' => $data['invoice_archived'],
                    'score_sales' => $data['scores']['sales'],
                    'score_growth' => $data['scores']['growth'],
                    'score_expiration' => $data['scores']['expiration'],
                    'score_inventory' => $data['scores']['inventory'],
                    'score_premium' => $data['scores']['premium'],
                    'score_invoice' => $data['scores']['invoice'],
                    'score_cleaning' => $data['scores']['cleaning'],
                    'score_strategy' => $data['scores']['strategy'],
                    'total_score' => $data['scores']['total'],
                    'score_loaded' => $data['scores']['invoice'] / 2, 
                    'score_registered' => $data['scores']['invoice'] / 4,
                    'score_ordered' => $data['scores']['invoice'] / 4,
                ]);
            }

            return true;
        });
    }

    private function calculateSales(int $userId, int $month, int $year): float
    {
        return round((float) Order::where('seller_id', $userId)
            ->whereMonth('order_date', $month)
            ->whereYear('order_date', $year)
            ->where('status', 'Completed')
            ->sum('total_amount_usd'), 2);
    }

    private function calculateGrowth(int $userId, float $currentSales, int $prevMonth, int $prevYear): float
    {
        $salesPrev = (float) Order::where('seller_id', $userId)
            ->whereMonth('order_date', $prevMonth)
            ->whereYear('order_date', $prevYear)
            ->where('status', 'Completed')
            ->sum('total_amount_usd');

        if ($salesPrev > 0) {
            return round((($currentSales - $salesPrev) / $salesPrev) * 100, 2);
        }

        // Si el mes pasado no hubo ventas, el crecimiento es 0% según requerimiento de usuario
        return 0.0;
    }

    private function calculateOrderDependentMetrics(Employee $employee, int $month, int $year): array
    {
        $expirations = 0;
        $premiumProducts = 0;
        $strategySales = 0;

        $assignedLabIds = $employee->laboratories->pluck('id')->toArray();
        $assignedProductIds = $employee->products->pluck('id')->toArray();

        $orders = Order::with(['details.product'])
            ->where('seller_id', $employee->user_id)
            ->whereMonth('order_date', $month)
            ->whereYear('order_date', $year)
            ->get();

        foreach ($orders as $order) {
            foreach ($order->details as $detail) {
                $product = $detail->product;
                if (!$product)
                    continue;

                if ($detail->unit_price_usd > 15) {
                    $premiumProducts += $detail->quantity;
                }

                if (in_array($product->id, $assignedProductIds) || in_array($product->laboratory_id, $assignedLabIds)) {
                    $strategySales += $detail->quantity;
                }

                if ($detail->quantity_expiration > 0) {
                    $expirations += $detail->quantity_expiration;
                }
            }
        }

        return [
            'expirations' => (int) $expirations,
            'premium_products' => (int) $premiumProducts,
            'strategy_sales' => (int) $strategySales,
        ];
    }

    private function calculateInventoryMetrics(int $userId, int $month, int $year): array
    {
        // 1. Conteos de Cíclicos
        $productCounts = ProductCount::where('user_id', $userId)
            ->whereMonth('created_at', $month)
            ->whereYear('created_at', $year)
            ->get();

        // 2. Conteos de Ventas
        $saleCounts = SaleCount::where('user_id', $userId)
            ->whereMonth('created_at', $month)
            ->whereYear('created_at', $year)
            ->get();

        // 3. Conteos de Facturas
        $invoiceCounts = InvoiceCount::where('user_id', $userId)
            ->whereMonth('created_at', $month)
            ->whereYear('created_at', $year)
            ->get();

        // Unificamos el total de conteos realizados
        $totalCounted = $productCounts->count() + $saleCounts->count() + $invoiceCounts->count();

        // Lógica de "Error": Cuando el usuario reportó discrepancia pero el admin corrigió (correction_difference > 0)
        // O cuando la discrepancia inicial fue desmentida por el admin (discrepancy inicial != 0, approved, discrefancy final = 0)
        $errors = 0;

        // Solo ProductCount tiene correction_difference actualmente
        foreach ($productCounts as $count) {
            if ($count->status === 'approved' && $count->correction_difference > 0) {
                $errors++;
            }
        }

        return [
            'inventory_counted' => $totalCounted,
            'inventory_errors' => $errors,
        ];
    }

    private function calculateCleaningMetrics(int $employeeId, int $month, int $year): array
    {
        $cleaningExecutions = CleaningActivityExecution::where('employee_id', $employeeId)
            ->whereMonth('scheduled_date', $month)
            ->whereYear('scheduled_date', $year)
            ->get();

        return [
            'cleaning_assigned' => $cleaningExecutions->count(),
            'cleaning_completed' => $cleaningExecutions->where('status', 'Completada')->count(),
        ];
    }

    private function calculateInvoiceMetrics(int $userId, int $month, int $year): array
    {
        // 1. Registered (Cabeceras creadas) - registered_by
        $registeredCount = Invoice::where('registered_by', $userId)
            ->whereMonth('created_at', $month)
            ->whereYear('created_at', $year)
            ->count();

        // 2. Loaded Items (Items cargados) - loaded_by
        $loadedItemsCount = Invoice::where('loaded_by', $userId)
            ->whereMonth('created_at', $month)
            ->whereYear('created_at', $year)
            ->withCount('details')
            ->get()
            ->sum('details_count');

        // 3. Ordered (Archivadas) - ordered_by
        $orderedCount = Invoice::where('ordered_by', $userId)
            ->whereMonth('created_at', $month)
            ->whereYear('created_at', $year)
            ->count();

        return [
            'invoice_headers' => $registeredCount,
            'invoice_items' => $loadedItemsCount,
            'invoice_archived' => $orderedCount,
        ];
    }
}
