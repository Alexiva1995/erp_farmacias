<?php

namespace App\Contracts;

use App\Models\Employee;
use Illuminate\Pagination\LengthAwarePaginator;

interface SocialBenefit
{
  public function index(array $data): LengthAwarePaginator;
  public function payment(Employee $employee, array $data): bool;
  public function getSettlementData(Employee $employee): array;
  public function fire(Employee $employee, array $data): bool;
}
