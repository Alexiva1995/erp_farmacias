<?php

namespace App\Services\EmployeePerformance;

use App\Models\Employee;
use App\Models\Order;
use App\Models\Invoice;
use App\Models\ProductCount;
use App\Models\CleaningActivityExecution;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class EmployeePerformanceQueryService
{
    /**
     * Get employees with calculated performance metrics for a specific month/year.
     */
    public function getEmployeesWithPerformance(int $month, int $year): Collection
    {
        // Dates for comparisons
        $currentDate = Carbon::createFromDate($year, $month, 1);
        $previousDate = $currentDate->copy()->subMonth();
        $prevMonth = $previousDate->month;
        $prevYear = $previousDate->year;

        // 1. Calculate RAW metrics for all employees
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

        // 2. Find MAX (Leader) values for Invoice metrics
        $maxInvoiceItems = $employeesData->max('metrics.invoice_items') ?: 1;
        $maxInvoiceHeaders = $employeesData->max('metrics.invoice_headers') ?: 1;
        $maxInvoiceArchived = $employeesData->max('metrics.invoice_archived') ?: 1;

        // 3. Normalize Scores and build Final Collection
        return $employeesData->map(function ($data) use ($maxInvoiceItems, $maxInvoiceHeaders, $maxInvoiceArchived) {
            $employee = $data['employee'];
            $metrics = $data['metrics'];

            // Calculate Scores based on Leaders
            $metrics['score_loaded'] = ($metrics['invoice_items'] / $maxInvoiceItems) * 5;
            $metrics['score_registered'] = ($metrics['invoice_headers'] / $maxInvoiceHeaders) * 2.5;
            $metrics['score_ordered'] = ($metrics['invoice_archived'] / $maxInvoiceArchived) * 2.5;

            return [
                'id' => $employee->id,
                'name' => $employee->name,
                'last_name' => $employee->last_name,
                'identification' => $employee->identification,
                'photo' => $employee->photo,
                ...$metrics
            ];
        });
    }

    private function calculateSales(int $userId, int $month, int $year): float
    {
        return (float) Order::where('seller_id', $userId)
            ->whereMonth('order_date', $month)
            ->whereYear('order_date', $year)
            ->where('status', 'Completed')
            ->sum('total_amount_usd');
    }

    private function calculateGrowth(int $userId, float $currentSales, int $prevMonth, int $prevYear): float
    {
        $salesPrev = Order::where('seller_id', $userId)
            ->whereMonth('order_date', $prevMonth)
            ->whereYear('order_date', $prevYear)
            ->sum('total_amount_usd');

        if ($salesPrev > 0) {
            return (($currentSales - $salesPrev) / $salesPrev) * 100;
        } elseif ($currentSales > 0) {
            return 100;
        }

        return 0;
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
        $productCounts = ProductCount::where('user_id', $userId)
            ->whereMonth('created_at', $month)
            ->whereYear('created_at', $year)
            ->get();

        return [
            'inventory_counted' => $productCounts->count(),
            'inventory_errors' => $productCounts->sum('correction_difference'),
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
