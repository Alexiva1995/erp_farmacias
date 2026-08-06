<?php

declare(strict_types=1);

namespace App\Services\EmployeePerformance;

use App\Models\Employee;
use App\Models\Order;
use App\Models\OrderDetail;
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
                            'photo' => $s->employee->photo_url ?? '',
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
                \Log::warning("No se pudo consultar snapshots (posiblemente tabla inexistente): " . $e->getMessage());
            }
        }

        // 1. Bulk pre-fetch all metrics for all active employees to avoid N+1 queries
        $employees = Employee::with(['laboratories:id', 'products:id'])
            ->where('is_active', true)
            ->select(['id', 'name', 'last_name', 'identification', 'photo', 'user_id'])
            ->orderByRaw('photo IS NOT NULL DESC')
            ->orderBy('name', 'ASC')
            ->get();

        // 1a. Bulk Sales & Previous Month Sales
        $salesMap = Order::whereMonth('order_date', $month)
            ->whereYear('order_date', $year)
            ->where('status', 'Completed')
            ->groupBy('seller_id')
            ->selectRaw('seller_id, ROUND(SUM(total_amount_usd), 2) as total')
            ->pluck('total', 'seller_id');

        $prevSalesMap = Order::whereMonth('order_date', $prevMonth)
            ->whereYear('order_date', $prevYear)
            ->where('status', 'Completed')
            ->groupBy('seller_id')
            ->selectRaw('seller_id, ROUND(SUM(total_amount_usd), 2) as total')
            ->pluck('total', 'seller_id');

        // 1b. Bulk Order Details for Order Dependent Metrics
        $orderDetailsGrouped = OrderDetail::join('orders', 'order_details.order_id', '=', 'orders.id')
            ->join('products', 'order_details.product_id', '=', 'products.id')
            ->whereMonth('orders.order_date', $month)
            ->whereYear('orders.order_date', $year)
            ->select([
                'orders.seller_id',
                'order_details.product_id',
                'products.laboratory_id',
                'order_details.unit_price_usd',
                'order_details.quantity',
                'order_details.quantity_expiration',
            ])
            ->get()
            ->groupBy('seller_id');

        // 1c. Bulk Inventory Counts
        $productCountMap = ProductCount::whereMonth('created_at', $month)->whereYear('created_at', $year)
            ->groupBy('user_id')->selectRaw('user_id, COUNT(*) as total')->pluck('total', 'user_id');

        $saleCountMap = SaleCount::whereMonth('created_at', $month)->whereYear('created_at', $year)
            ->groupBy('user_id')->selectRaw('user_id, COUNT(*) as total')->pluck('total', 'user_id');

        $invoiceCountMap = InvoiceCount::whereMonth('created_at', $month)->whereYear('created_at', $year)
            ->groupBy('user_id')->selectRaw('user_id, COUNT(*) as total')->pluck('total', 'user_id');

        $productErrorMap = ProductCount::whereMonth('created_at', $month)->whereYear('created_at', $year)
            ->where('status', 'approved')->where('correction_difference', '>', 0)
            ->groupBy('user_id')->selectRaw('user_id, COUNT(*) as total')->pluck('total', 'user_id');

        // 1d. Bulk Invoice Metrics
        $invoiceHeadersMap = Invoice::whereNotNull('registered_by')
            ->whereMonth('created_at', $month)->whereYear('created_at', $year)
            ->groupBy('registered_by')->selectRaw('registered_by, COUNT(*) as total')->pluck('total', 'registered_by');

        $invoiceItemsMap = DB::table('invoices')
            ->join('invoice_details', 'invoices.id', '=', 'invoice_details.invoice_id')
            ->whereNotNull('invoices.loaded_by')
            ->whereMonth('invoices.created_at', $month)->whereYear('invoices.created_at', $year)
            ->groupBy('invoices.loaded_by')->selectRaw('invoices.loaded_by, COUNT(invoice_details.id) as total')->pluck('total', 'invoices.loaded_by');

        $invoiceArchivedMap = Invoice::whereNotNull('ordered_by')
            ->whereMonth('created_at', $month)->whereYear('created_at', $year)
            ->groupBy('ordered_by')->selectRaw('ordered_by, COUNT(*) as total')->pluck('total', 'ordered_by');

        // 1e. Bulk Cleaning Metrics
        $cleaningMap = CleaningActivityExecution::whereMonth('scheduled_date', $month)->whereYear('scheduled_date', $year)
            ->groupBy('employee_id')
            ->selectRaw("employee_id, COUNT(*) as total_assigned, SUM(CASE WHEN status = 'Completada' THEN 1 ELSE 0 END) as total_completed")
            ->get()
            ->keyBy('employee_id');

        // Map employee data without N+1 queries
        $employeesData = $employees->map(function ($employee) use (
            $salesMap, $prevSalesMap, $orderDetailsGrouped, $productCountMap,
            $saleCountMap, $invoiceCountMap, $productErrorMap,
            $invoiceHeadersMap, $invoiceItemsMap, $invoiceArchivedMap, $cleaningMap
        ) {
            $userId = $employee->user_id;

            $sales = $userId ? (float) ($salesMap[$userId] ?? 0.0) : 0.0;
            $prevSales = $userId ? (float) ($prevSalesMap[$userId] ?? 0.0) : 0.0;
            $growth = $prevSales > 0 ? round((($sales - $prevSales) / $prevSales) * 100, 2) : 0.0;

            $expirations = 0;
            $premiumProducts = 0;
            $strategySales = 0;

            if ($userId && isset($orderDetailsGrouped[$userId])) {
                $assignedLabIds = $employee->laboratories->pluck('id')->flip()->toArray();
                $assignedProductIds = $employee->products->pluck('id')->flip()->toArray();

                foreach ($orderDetailsGrouped[$userId] as $detail) {
                    if ($detail->unit_price_usd > 15) {
                        $premiumProducts += $detail->quantity;
                    }
                    if (isset($assignedProductIds[$detail->product_id]) || isset($assignedLabIds[$detail->laboratory_id])) {
                        $strategySales += $detail->quantity;
                    }
                    if ($detail->quantity_expiration > 0) {
                        $expirations += $detail->quantity_expiration;
                    }
                }
            }

            $inventoryCounted = $userId ? (
                ($productCountMap[$userId] ?? 0) +
                ($saleCountMap[$userId] ?? 0) +
                ($invoiceCountMap[$userId] ?? 0)
            ) : 0;

            $inventoryErrors = $userId ? ($productErrorMap[$userId] ?? 0) : 0;

            $cleaning = $cleaningMap[$employee->id] ?? null;

            $metrics = [
                'sales' => $sales,
                'growth' => $growth,
                'expirations' => (int) $expirations,
                'inventory_counted' => (int) $inventoryCounted,
                'inventory_errors' => (int) $inventoryErrors,
                'premium_products' => (int) $premiumProducts,
                'cleaning_assigned' => $cleaning ? (int) $cleaning->total_assigned : 0,
                'cleaning_completed' => $cleaning ? (int) $cleaning->total_completed : 0,
                'strategy_sales' => (int) $strategySales,
                'invoice_items' => $userId ? (int) ($invoiceItemsMap[$userId] ?? 0) : 0,
                'invoice_headers' => $userId ? (int) ($invoiceHeadersMap[$userId] ?? 0) : 0,
                'invoice_archived' => $userId ? (int) ($invoiceArchivedMap[$userId] ?? 0) : 0,
            ];

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

            if ($maxGrowth == 0) {
                $growthScore = ($metrics['growth'] == 0) ? 15 : 0;
            } elseif ($maxGrowth > 0) {
                $growthScore = ($metrics['growth'] / $maxGrowth) * 15;
            } else {
                $growthScore = ($metrics['growth'] != 0) ? ($maxGrowth / $metrics['growth']) * 15 : 0;
            }

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
                'photo' => $employee->photo_url ?? '',
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
}
