<?php

declare(strict_types=1);

namespace App\Services;

use App\Contracts\Payslip;
use App\Exports\PayslipsExport;
use App\Models\Employee;
use App\Models\Payslip as MPayslip;
use App\Repositories\PayslipRepository;
use Carbon\Carbon;
use Illuminate\Pagination\LengthAwarePaginator;

class PayslipServices implements Payslip
{
  public function __construct(protected PayslipRepository $payslipRepository)
  {
  }

  public function index(array $data): LengthAwarePaginator
  {
    return $this->payslipRepository->index($data);
  }

  public function generate(Carbon $date): bool
  {
    $name = $this->generateName($date);
    $details = $this->payslipRepository->getEligibleSalaryDetails($date);

    return $this->payslipRepository->generate($date, $name, $details);
  }

  public function getEmployeeVouchers(MPayslip $payslip, Employee $employee): array
  {
    return $this->payslipRepository->getEmployeeVouchers($payslip, $employee);
  }

  public function updateVouchers(MPayslip $payslip, array $details): bool
  {
    return $this->payslipRepository->updateDetails($payslip, $details);
  }

  public function finalize(MPayslip $payslip, array $data): bool
  {
    return $this->payslipRepository->finalize($payslip, $data);
  }

  public function exportExcel(MPayslip $payslip): PayslipsExport
  {
    $query = $this->payslipRepository->exportableData($payslip, 'legal');
    return new PayslipsExport($query);
  }

  public function getData(MPayslip $payslip, string $type): array
  {
    $type = ($type === 'full' || $type === 'eye') ? 'full' : 'legal';
    $query = $this->payslipRepository->getData($payslip, $type);
    return $query;
  }

  private function generateName(Carbon $date): string
  {
    $month = $date->locale('es')->monthName;
    $type = $date->day === 15 ? 'Nomina quincena' : 'Nomina fin de mes';
    return "$type ($month)";
  }
}
