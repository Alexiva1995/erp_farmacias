<?php

namespace App\Contracts\Repositories;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface FinancialStatementRepositoryInterface
{
    public function getIncomeByCurrency(?string $startDate, ?string $endDate, ?string $search = null): array;
    
    public function getCostsByCurrency(?string $startDate, ?string $endDate, ?string $search = null): array;
    
    public function getExpensesUsdSum(?string $startDate, ?string $endDate, ?string $search = null): float;
    
    public function getExpensesByCurrency(?string $startDate, ?string $endDate, ?string $search = null): array;
    
    public function getPaginatedDetails(?string $startDate, ?string $endDate, ?string $search, ?string $type, int $perPage = 50): LengthAwarePaginator;
}
