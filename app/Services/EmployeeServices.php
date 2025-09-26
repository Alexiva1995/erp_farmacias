<?php

namespace App\Services;

use App\Contracts\Employee;
use App\Models\Employee as MEmployee;
use App\Models\UsersSalaryDetails;
use App\Repository\EmployeeRepository;
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
    $validFiles = ['rif', 'residence_letter', 'cv'];
    if (!in_array($file, $validFiles)) {
      throw new Exception('Documento inválido');
    }

    $path = $employee->$file;
    if (empty($path)) {
      throw new Exception('El archivo no existe');
    }

    return $this->employeeRepository->downloadDocument($path);
  }

  public function reset2FA(MEmployee $employee): bool
  {
    return $this->employeeRepository->reset2FA($employee);
  }
}
