<?php

namespace App\Contracts;

use App\Exports\PayslipsExport;
use App\Models\Employee;
use App\Models\Payslip as MPayslip;
use Carbon\Carbon;
use Illuminate\Pagination\LengthAwarePaginator;

interface Payslip
{
  public function index(array $data): LengthAwarePaginator;
  public function generate(Carbon $date): bool;
  public function finalize(MPayslip $payslip, array $data): bool;
  public function exportExcel(MPayslip $payslip): PayslipsExport;
  public function getData(MPayslip $payslip, string $type): array;
  public function getEmployeeVouchers(MPayslip $payslip, Employee $employee): array;
  public function updateVouchers(MPayslip $payslip, array $data): bool;
}
