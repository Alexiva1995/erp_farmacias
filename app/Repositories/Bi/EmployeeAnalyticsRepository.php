<?php

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

        // Esta consulta base servirá para alimentar los rankings y KPIs generales
        $employees = Employee::where('is_active', true)->get();
        
        $data = $employees->map(function ($employee) use ($startDate, $endDate) {
            return $this->getMetricsForEmployee($employee, $startDate, $endDate);
        });

        return $data->toArray();
    }

    public function getEmployeeComparison(int $employeeAId, int $employeeBId, array $filters): array
    {
        $startDate = $filters['start_date'] ?? now()->startOfMonth()->format('Y-m-d');
        $endDate = $filters['end_date'] ?? now()->format('Y-m-d');

        $empA = Employee::findOrFail($employeeAId);
        $empB = Employee::findOrFail($employeeBId);

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
        $employee = Employee::findOrFail($employeeId);

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
        $history = [];
        $userId = $employee->user_id;

        for ($i = $months - 1; $i >= 0; $i--) {
            $date = now()->subMonths($i);
            $month = $date->month;
            $year = $date->year;

            $sales = DB::table('orders')
                ->where('seller_id', $userId)
                ->whereMonth('order_date', $month)
                ->whereYear('order_date', $year)
                ->where('status', 'Completed')
                ->sum('total_amount_usd');

            $units = DB::table('orders')
                ->join('order_details', 'orders.id', '=', 'order_details.order_id')
                ->where('orders.seller_id', $userId)
                ->whereMonth('orders.order_date', $month)
                ->whereYear('orders.order_date', $year)
                ->where('orders.status', 'Completed')
                ->sum('order_details.quantity');

            $history[] = [
                'label' => $date->format('M Y'),
                'sales' => (float)$sales,
                'units' => (int)$units
            ];
        }

        return $history;
    }
}
