<?php

namespace App\Contracts;

interface EmployeeAnalytics
{
    public function getDashboardData(array $filters): array;
    public function getEmployeeComparison(int $employeeAId, int $employeeBId, array $filters): array;
    public function getEmployeeRanking(array $filters): array;
    public function getEmployeeDetail(int $employeeId, array $filters): array;
}
