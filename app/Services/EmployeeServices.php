<?php

declare(strict_types=1);

namespace App\Services;

use App\Contracts\Employee;
use App\Models\Employee as MEmployee;
use App\Models\UsersSalaryDetails;
use App\Repositories\EmployeeRepository;
use Exception;
use Illuminate\Pagination\LengthAwarePaginator;
use Symfony\Component\HttpFoundation\StreamedResponse;

class EmployeeServices implements Employee
{
  public function __construct(
    protected EmployeeRepository $employeeRepository
  ) {
  }

  public function list(array $data): LengthAwarePaginator
  {
    return $this->employeeRepository->list($data);
  }

  public function store(array $data): bool
  {
    return $this->employeeRepository->store($data);
  }

  public function fire(MEmployee $employee): bool
  {
    return $this->employeeRepository->fire($employee);
  }

  public function update(MEmployee $employee, array $data): bool
  {
    return $this->employeeRepository->update($employee, $data);
  }

  public function profile(MEmployee $employee): MEmployee|null
  {
    return $this->employeeRepository->profile($employee);
  }

  public function storeVoucher(MEmployee $employee, array $data): bool
  {
    return $this->employeeRepository->storeVoucher($employee, $data);
  }

  public function getVouchers(MEmployee $employee): LengthAwarePaginator
  {
    return $this->employeeRepository->getVouchers($employee);
  }

  public function deleteVoucher(UsersSalaryDetails $voucher): bool
  {
    return $this->employeeRepository->deleteVoucher($voucher);
  }

  public function deleteEmployee(MEmployee $employee): bool
  {
    return $this->employeeRepository->deleteEmployee($employee);
  }

  public function storeDocuments(MEmployee $employee, array $data): bool
  {
    return $this->employeeRepository->storeDocuments($employee, $data);
  }

  public function downloadDocument(MEmployee $employee, string $file): Exception|StreamedResponse
  {
    return $this->employeeRepository->downloadDocument($employee, $file);
  }

  public function reset2FA(MEmployee $employee): bool
  {
    return $this->employeeRepository->reset2FA($employee);
  }

  /** @return array{ calculation?: array, history: array, employee: array } */
  public function getPayments(MEmployee $employee, array $data): array
  {
    return $this->employeeRepository->getPayments($employee, $data);
  }

  /** @return array calculation and updated employee saldo_deuda */
  public function runPaymentCalculation(MEmployee $employee, array $data): array
  {
    return $this->employeeRepository->runPaymentCalculation($employee, $data);
  }

  public function setHealthConsumption(MEmployee $employee, int $year, int $month, float $amount): void
  {
    $this->employeeRepository->setHealthConsumption($employee, $year, $month, $amount);
  }

  public function updatePayrollSettings(MEmployee $employee, array $data): array
  {
    return $this->employeeRepository->updatePayrollSettings($employee, $data);
  }
}
