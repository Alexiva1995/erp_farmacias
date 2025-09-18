<?php

namespace App\Contracts;

use App\Models\UsersSalaryDetails;
use Exception;
use Illuminate\Pagination\LengthAwarePaginator;
use App\Models\Employee as MEmployee;
use Symfony\Component\HttpFoundation\StreamedResponse;

interface Employee
{
  public function list(array $data): LengthAwarePaginator;
  public function store(array $data): bool;
  public function fire(MEmployee $employee): bool;
  public function update(MEmployee $employee, array $data): bool;
  public function profile(MEmployee $employee): MEmployee|null;
  public function storeVoucher(MEmployee $employee, array $data): bool;
  public function getVouchers(MEmployee $employee): LengthAwarePaginator;
  public function deleteVoucher(UsersSalaryDetails $voucher): bool;
  public function deleteEmployee(MEmployee $employee): bool;
  public function storeDocuments(MEmployee $employee, array $data): bool;
  public function downloadDocument(MEmployee $employee, string $file): Exception|StreamedResponse;
}
