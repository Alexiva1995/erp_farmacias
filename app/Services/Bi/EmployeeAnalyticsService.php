<?php

namespace App\Services\Bi;

use App\Contracts\EmployeeAnalytics as EmployeeAnalyticsRepository;
use Carbon\Carbon;

class EmployeeAnalyticsService
{
    public function __construct(
        protected EmployeeAnalyticsRepository $repository
    ) {}

    public function getFullDashboard(array $filters): array
    {
        $rawData = $this->repository->getDashboardData($filters);
        
        $processedData = collect($rawData)->map(function ($item) {
            $item['points'] = $this->calculatePoints($item);
            $item['conversion_rate'] = $item['quotations'] > 0 ? ($item['tickets'] / $item['quotations']) * 100 : 0;
            $item['task_completion_rate'] = $item['tasks_assigned'] > 0 ? ($item['tasks_completed'] / $item['tasks_assigned']) * 100 : 0;
            return $item;
        });

        // Determinar Ranking y Hall of Fame
        $hallOfFame = [
            'employee_of_the_month' => $processedData->sortByDesc('points')->first(),
            'top_seller' => $processedData->sortByDesc('sales')->first(),
            'operational_star' => $processedData->sortByDesc('tasks_completed')->first(),
            'inventory_expert' => $processedData->sortByDesc('inventory_counted')->first(),
        ];

        return [
            'employees' => $processedData->sortByDesc('points')->values()->toArray(),
            'hall_of_fame' => $hallOfFame,
            'summary' => [
                'total_sales' => $processedData->sum('sales'),
                'total_units' => $processedData->sum('units'),
                'total_tasks' => $processedData->sum('tasks_completed'),
            ]
        ];
    }

    public function getComparison(int $empA, int $empB, array $filters): array
    {
        $data = $this->repository->getEmployeeComparison($empA, $empB, $filters);
        
        $data['employee_a']['points'] = $this->calculatePoints($data['employee_a']);
        $data['employee_b']['points'] = $this->calculatePoints($data['employee_b']);

        return $data;
    }

    public function getDetail(int $id, array $filters): array
    {
        $detail = $this->repository->getEmployeeDetail($id, $filters);
        $detail['metrics']['points'] = $this->calculatePoints($detail['metrics']);
        $detail['metrics']['conversion_rate'] = $detail['metrics']['quotations'] > 0 ? ($detail['metrics']['tickets'] / $detail['metrics']['quotations']) * 100 : 0;
        
        return $detail;
    }

    private function calculatePoints(array $m): int
    {
        $p = 0;
        // Ventas: 1 punto cada 10 USD
        $p += ($m['sales'] / 10);
        
        // Unidades Estratégicas: 10 puntos c/u
        $p += ($m['strategic_units'] * 10);
        
        // Unidades por vencer: 15 puntos c/u (Incentivo crítico)
        $p += ($m['expiring_units'] * 15);
        
        // Inventario: 5 puntos por conteo, -50 por error detectado
        $p += ($m['inventory_counted'] * 5);
        $p -= ($m['inventory_errors'] * 50);
        
        // Tareas: 20 puntos por tarea completada
        $p += ($m['tasks_completed'] * 20);
        
        // Productividad administrativa: 5 puntos por factura
        $p += ($m['invoices_processed'] * 5);

        return (int) max(0, $p);
    }
}
