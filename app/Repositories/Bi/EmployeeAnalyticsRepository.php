<?php

declare(strict_types=1);

namespace App\Repositories\Bi;

use App\Contracts\EmployeeAnalytics;
use App\Models\Employee;
use App\Models\Order;
use App\Models\Quotation;
use App\Models\ProductCount;
use App\Models\CleaningActivityExecution;
use App\Models\Invoice;
use App\Models\EmployeePerformanceSnapshot;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class EmployeeAnalyticsRepository implements EmployeeAnalytics
{
    public function getDashboardData(array $filters): array
    {
        $startDate = $filters['start_date'] ?? now()->startOfMonth()->format('Y-m-d');
        $endDate = $filters['end_date'] ?? now()->format('Y-m-d');

        $employees = Employee::with(['laboratories:id', 'products:id'])->where('is_active', true)->get();

        // 1. Obtener métricas agregadas de Órdenes en lote para evitar N+1
        $salesMetrics = DB::table('orders')
            ->whereBetween('order_date', [$startDate, $endDate])
            ->where('status', 'Completed')
            ->select(
                'seller_id',
                DB::raw('SUM(total_amount_usd) as total_sales'),
                DB::raw('COUNT(*) as ticket_count')
            )
            ->groupBy('seller_id')
            ->get()
            ->keyBy('seller_id');

        $unitsSoldMetrics = DB::table('orders')
            ->join('order_details', 'orders.id', '=', 'order_details.order_id')
            ->whereBetween('orders.order_date', [$startDate, $endDate])
            ->where('orders.status', 'Completed')
            ->select('orders.seller_id', DB::raw('SUM(order_details.quantity) as total_units'))
            ->groupBy('orders.seller_id')
            ->get()
            ->keyBy('seller_id');

        $expiringSoldMetrics = DB::table('orders')
            ->join('order_details', 'orders.id', '=', 'order_details.order_id')
            ->whereBetween('orders.order_date', [$startDate, $endDate])
            ->where('orders.status', 'Completed')
            ->select('orders.seller_id', DB::raw('SUM(order_details.quantity_expiration) as total_expiring'))
            ->groupBy('orders.seller_id')
            ->get()
            ->keyBy('seller_id');

        // 2. Tareas de limpieza en lote
        $tasksMetrics = CleaningActivityExecution::whereBetween('scheduled_date', [$startDate, $endDate])
            ->select(
                'employee_id',
                DB::raw('COUNT(*) as total_assigned'),
                DB::raw('SUM(CASE WHEN status = "Completada" THEN 1 ELSE 0 END) as completed')
            )
            ->groupBy('employee_id')
            ->get()
            ->keyBy('employee_id');

        // 3. Inventarios en lote
        $inventoryMetrics = ProductCount::whereBetween('created_at', [$startDate . ' 00:00:00', $endDate . ' 23:59:59'])
            ->select(
                'user_id',
                DB::raw('COUNT(*) as total_counted'),
                DB::raw('SUM(CASE WHEN status = "rejected" OR correction_difference > 0 THEN 1 ELSE 0 END) as errors')
            )
            ->groupBy('user_id')
            ->get()
            ->keyBy('user_id');

        // 4. Cotizaciones en lote
        $quotationsMetrics = Quotation::whereBetween('created_at', [$startDate . ' 00:00:00', $endDate . ' 23:59:59'])
            ->select('created_by', DB::raw('COUNT(*) as total_quotations'))
            ->groupBy('created_by')
            ->get()
            ->keyBy('created_by');

        // 6. Facturas procesadas en lote
        $invoicesMetrics = Invoice::whereBetween('created_at', [$startDate . ' 00:00:00', $endDate . ' 23:59:59'])
            ->select(
                DB::raw('COALESCE(registered_by, loaded_by, ordered_by) as user_id'),
                DB::raw('COUNT(*) as total_invoices')
            )
            ->groupBy('user_id')
            ->get()
            ->keyBy('user_id');

        // 7. Unidades Estratégicas en lote (1 sola consulta SQL batch para todos los empleados)
        $userIds = $employees->pluck('user_id')->filter()->toArray();
        $strategicBatchMetrics = collect();
        if (!empty($userIds)) {
            $strategicBatchMetrics = DB::table('orders')
                ->join('order_details', 'orders.id', '=', 'order_details.order_id')
                ->join('products', 'products.id', '=', 'order_details.product_id')
                ->whereIn('orders.seller_id', $userIds)
                ->whereBetween('orders.order_date', [$startDate, $endDate])
                ->where('orders.status', 'Completed')
                ->select(
                    'orders.seller_id',
                    'products.laboratory_id',
                    'products.id as product_id',
                    DB::raw('SUM(order_details.quantity) as qty')
                )
                ->groupBy('orders.seller_id', 'products.laboratory_id', 'products.id')
                ->get()
                ->groupBy('seller_id');
        }

        $data = $employees->map(function ($employee) use ($startDate, $endDate, $salesMetrics, $unitsSoldMetrics, $expiringSoldMetrics, $tasksMetrics, $inventoryMetrics, $quotationsMetrics, $invoicesMetrics, $strategicBatchMetrics) {
            $userId = $employee->user_id;
            $employeeId = $employee->id;

            $sales = $salesMetrics->get($userId);
            $units = $unitsSoldMetrics->get($userId);
            $expiring = $expiringSoldMetrics->get($userId);
            $tasks = $tasksMetrics->get($employeeId);
            $inv = $inventoryMetrics->get($userId);
            $quotes = $quotationsMetrics->get($userId);
            $invs = $invoicesMetrics->get($userId);

            // Productos Estratégicos (Cálculo en memoria a partir de la consulta batch)
            $assignedLabIds = $employee->laboratories->pluck('id')->toArray();
            $assignedProdIds = $employee->products->pluck('id')->toArray();
            $strategicUnits = 0;
            
            if (!empty($assignedLabIds) || !empty($assignedProdIds)) {
                $userStrategicRows = $strategicBatchMetrics->get($userId, collect());
                $strategicUnits = $userStrategicRows->filter(function ($row) use ($assignedLabIds, $assignedProdIds) {
                    return in_array($row->laboratory_id, $assignedLabIds) || in_array($row->product_id, $assignedProdIds);
                })->sum('qty');
            }

            return [
                'id' => $employeeId,
                'name' => $employee->name,
                'last_name' => $employee->last_name,
                'photo' => $employee->photo_url,
                'sales' => (float)($sales->total_sales ?? 0),
                'tickets' => (int)($sales->ticket_count ?? 0),
                'units' => (int)($units->total_units ?? 0),
                'avg_ticket' => isset($sales->ticket_count) && $sales->ticket_count > 0 ? (float)($sales->total_sales / $sales->ticket_count) : 0,
                'strategic_units' => (int)$strategicUnits,
                'expiring_units' => (int)($expiring->total_expiring ?? 0),
                'inventory_counted' => (int)($inv->total_counted ?? 0),
                'inventory_errors' => (int)($inv->errors ?? 0),
                'tasks_assigned' => (int)($tasks->total_assigned ?? 0),
                'tasks_completed' => (int)($tasks->completed ?? 0),
                'quotations' => (int)($quotes->total_quotations ?? 0),
                'invoices_processed' => (int)($invs->total_invoices ?? 0),
            ];
        });

        return $data->toArray();
    }

    public function getEmployeeComparison(int $employeeAId, int $employeeBId, array $filters): array
    {
        $startDate = $filters['start_date'] ?? now()->startOfMonth()->format('Y-m-d');
        $endDate = $filters['end_date'] ?? now()->format('Y-m-d');

        $empA = Employee::with(['laboratories:id', 'products:id'])->findOrFail($employeeAId);
        $empB = Employee::with(['laboratories:id', 'products:id'])->findOrFail($employeeBId);

        return [
            'employee_a' => $this->getMetricsForEmployee($empA, $startDate, $endDate),
            'employee_b' => $this->getMetricsForEmployee($empB, $startDate, $endDate),
        ];
    }

    public function getEmployeeRanking(array $filters): array
    {
        return $this->getDashboardData($filters);
    }

    public function getEmployeeDetail(int $employeeId, array $filters): array
    {
        $startDate = $filters['start_date'] ?? now()->startOfMonth()->format('Y-m-d');
        $endDate = $filters['end_date'] ?? now()->format('Y-m-d');
        $employee = Employee::with(['laboratories:id', 'products:id'])->findOrFail($employeeId);

        $metrics = $this->getMetricsForEmployee($employee, $startDate, $endDate);
        
        // Añadir histórico mensual para el gráfico de doble eje
        $history = $this->getEmployeeMonthlyHistory($employee, 6);

        return [
            'metrics' => $metrics,
            'history' => $history
        ];
    }

    private function getMetricsForEmployee(Employee $employee, $startDate, $endDate): array
    {
        $userId = $employee->user_id;
        $employeeId = $employee->id;

        // 1. Ventas y Unidades
        $salesData = DB::table('orders')
            ->where('seller_id', $userId)
            ->whereBetween('order_date', [$startDate, $endDate])
            ->where('status', 'Completed')
            ->select(
                DB::raw('SUM(total_amount_usd) as total_sales'),
                DB::raw('COUNT(*) as ticket_count')
            )
            ->first();

        $unitsSold = DB::table('orders')
            ->join('order_details', 'orders.id', '=', 'order_details.order_id')
            ->where('orders.seller_id', $userId)
            ->whereBetween('orders.order_date', [$startDate, $endDate])
            ->where('orders.status', 'Completed')
            ->sum('order_details.quantity');

        // 2. Productos Estratégicos (Basado en laboratorios o productos asignados)
        $assignedLabIds = $employee->laboratories->pluck('id')->toArray();
        $assignedProdIds = $employee->products->pluck('id')->toArray();

        $strategicUnits = 0;
        if (!empty($assignedLabIds) || !empty($assignedProdIds)) {
            $strategicUnits = DB::table('orders')
                ->join('order_details', 'orders.id', '=', 'order_details.order_id')
                ->join('products', 'products.id', '=', 'order_details.product_id')
                ->where('orders.seller_id', $userId)
                ->whereBetween('orders.order_date', [$startDate, $endDate])
                ->where('orders.status', 'Completed')
                ->where(function($q) use ($assignedLabIds, $assignedProdIds) {
                    $q->whereIn('products.laboratory_id', $assignedLabIds)
                      ->orWhereIn('products.id', $assignedProdIds);
                })
                ->sum('order_details.quantity');
        }

        // 3. Productos por vencer vendidos (quantity_expiration > 0)
        $expiringSold = DB::table('orders')
            ->join('order_details', 'orders.id', '=', 'order_details.order_id')
            ->where('orders.seller_id', $userId)
            ->whereBetween('orders.order_date', [$startDate, $endDate])
            ->where('orders.status', 'Completed')
            ->sum('order_details.quantity_expiration');

        // 4. Inventarios
        $inventoryCounts = ProductCount::where('user_id', $userId)
            ->whereBetween('created_at', [$startDate . ' 00:00:00', $endDate . ' 23:59:59'])
            ->select(
                DB::raw('COUNT(*) as total_counted'),
                DB::raw('SUM(CASE WHEN status = "rejected" OR correction_difference > 0 THEN 1 ELSE 0 END) as errors')
            )
            ->first();

        // 5. Tareas (Limpieza)
        $tasks = CleaningActivityExecution::where('employee_id', $employeeId)
            ->whereBetween('scheduled_date', [$startDate, $endDate])
            ->select(
                DB::raw('COUNT(*) as total_assigned'),
                DB::raw('SUM(CASE WHEN status = "Completada" THEN 1 ELSE 0 END) as completed')
            )
            ->first();

        // 6. Conversión (Cotizaciones -> Facturas)
        $quotations = Quotation::where('created_by', $userId)
            ->whereBetween('created_at', [$startDate . ' 00:00:00', $endDate . ' 23:59:59'])
            ->count();

        // 7. Facturas cargadas (Productividad administrativa)
        $invoices = Invoice::whereBetween('created_at', [$startDate . ' 00:00:00', $endDate . ' 23:59:59'])
            ->where(function($q) use ($userId) {
                $q->where('registered_by', $userId)
                  ->orWhere('loaded_by', $userId)
                  ->orWhere('ordered_by', $userId);
            })
            ->count();

        return [
            'id' => $employeeId,
            'name' => $employee->name,
            'last_name' => $employee->last_name,
            'photo' => $employee->photo_url,
            'sales' => (float)($salesData->total_sales ?? 0),
            'tickets' => (int)($salesData->ticket_count ?? 0),
            'units' => (int)($unitsSold ?? 0),
            'avg_ticket' => $salesData->ticket_count > 0 ? (float)($salesData->total_sales / $salesData->ticket_count) : 0,
            'strategic_units' => (int)($strategicUnits ?? 0),
            'expiring_units' => (int)($expiringSold ?? 0),
            'inventory_counted' => (int)($inventoryCounts->total_counted ?? 0),
            'inventory_errors' => (int)($inventoryCounts->errors ?? 0),
            'tasks_assigned' => (int)($tasks->total_assigned ?? 0),
            'tasks_completed' => (int)($tasks->completed ?? 0),
            'quotations' => (int)($quotations ?? 0),
            'invoices_processed' => (int)($invoices ?? 0),
        ];
    }

    private function getEmployeeMonthlyHistory(Employee $employee, int $months): array
    {
        $userId = $employee->user_id;
        $startDate = now()->subMonths($months - 1)->startOfMonth();
        $endDate = now()->endOfMonth();

        // Agrupación directa por año y mes en una única consulta batch SQL
        $salesByMonth = DB::table('orders')
            ->where('seller_id', $userId)
            ->whereBetween('order_date', [$startDate->format('Y-m-d'), $endDate->format('Y-m-d')])
            ->where('status', 'Completed')
            ->select(
                DB::raw('YEAR(order_date) as yr'),
                DB::raw('MONTH(order_date) as mo'),
                DB::raw('SUM(total_amount_usd) as sales')
            )
            ->groupBy('yr', 'mo')
            ->get()
            ->keyBy(fn ($item) => sprintf('%04d-%02d', $item->yr, $item->mo));

        $unitsByMonth = DB::table('orders')
            ->join('order_details', 'orders.id', '=', 'order_details.order_id')
            ->where('orders.seller_id', $userId)
            ->whereBetween('orders.order_date', [$startDate->format('Y-m-d'), $endDate->format('Y-m-d')])
            ->where('orders.status', 'Completed')
            ->select(
                DB::raw('YEAR(orders.order_date) as yr'),
                DB::raw('MONTH(orders.order_date) as mo'),
                DB::raw('SUM(order_details.quantity) as units')
            )
            ->groupBy('yr', 'mo')
            ->get()
            ->keyBy(fn ($item) => sprintf('%04d-%02d', $item->yr, $item->mo));

        $history = [];
        for ($i = $months - 1; $i >= 0; $i--) {
            $date = now()->subMonths($i);
            $key = $date->format('Y-m');

            $history[] = [
                'label' => $date->format('M Y'),
                'sales' => (float)($salesByMonth->get($key)->sales ?? 0),
                'units' => (int)($unitsByMonth->get($key)->units ?? 0)
            ];
        }

        return $history;
    }
}
