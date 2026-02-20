<?php

namespace App\Repository;

use App\Models\Employee;
use App\Models\EmployeeHealthConsumption;
use App\Models\EmployeePaymentCalculation;
use App\Models\ExchangeRate;
use App\Models\Role;
use App\Models\SalaryConcept;
use App\Models\User;
use App\Models\UsersSalaryDetails;
use Exception;
use Illuminate\Http\UploadedFile;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class EmployeeRepository
{
  public function list(array $data): LengthAwarePaginator
  {
    $search = $data['search'] ?? '';
    $perPage = $data['perPage'] ?? 10;
    $active = filter_var($data['active'] ?? true, FILTER_VALIDATE_BOOLEAN);

    return Employee::query()
      ->select([
        'employees.id as id',
        'name',
        'last_name',
        'identification',
        'employees.is_active as is_active',
        'users.email as email',
        "users.role_id"
      ])
      ->leftJoin('users', 'users.id', '=', 'employees.user_id')
      ->when(!empty($search), function ($query) use ($search) {
        $query->orWhere('name', 'like', "%$search%")
          ->orWhere('last_name', 'like', "%$search%")
          ->orWhere('users.email', 'like', "%$search%")
          ->orWhere('identification', 'like', "%$search%");
      })
      ->when(auth()->user()->role_id === 3, function ($query) {
        $query->where('employees.user_id', auth()->id());
      })
      ->where('employees.is_active', '=', $active)
      ->paginate($perPage);
  }

  public function store(array $data): bool
  {
    $username = $data['name'] . " " . $data["last_name"];
    $role = $data['role'];
    $email = $data['email'];
    $password = $data['password'];
    $role_id = Role::find($role)->id;

    $user = User::create([
      'username' => $username,
      'role_id' => $role_id,
      "email" => $email,
      'is_active' => true,
      'password_hash' => Hash::make($password),
    ]);

    $concept = SalaryConcept::create([
      'name' => 'Bono de Alimentación',
      'type' => 'salary',
      'frequency' => 'monthly'
    ]);

    $user->salaries()->create([
      'amount' => 40,
      'salary_concept_id' => $concept->id
    ]);

    unset($role);
    unset($email);
    unset($password);

    $data = array_merge($data, ['is_active' => true]);
    $user->employee()->create($data);

    return !empty($user);
  }

  public function fire(Employee $employee): bool
  {
    $employee->update(['is_active' => false]);

    return true;
  }

  public function update(Employee $employee, array $data): bool
  {
    $username = $data['name'] . " " . $data["last_name"];
    $role = $data['role'] ?? $employee->user?->role_id;
    $email = $data['email'];
    $role_id = $role ? Role::find($role)?->id ?? $employee->user?->role_id : $employee->user?->role_id;

    $userData = [
      'username' => $username,
      'role_id' => $role_id,
      "email" => $email,
    ];

    if (!empty($data['password'])) {
      $userData['password_hash'] = Hash::make($data['password']);
    }

    $employee->user()->update($userData);

    unset($role, $email, $data['password'], $data['role']);

    $employee->update($data);

    return !empty($employee->user);
  }

  public function profile(Employee $employee): Employee|null
  {
    $query = $employee->load('user.role');

    return $query;
  }

  public function storeVoucher(Employee $employee, array $data): bool
  {
    $salary_concept = SalaryConcept::create($data);
    $rre = $employee->user->salaries()->create([
      ...$data,
      'salary_concept_id' => $salary_concept->id
    ]);

    return true;
  }

  public function getVouchers(Employee $employee): LengthAwarePaginator
  {
    $results = $employee->user->salaries()->with('concept')->paginate(10);
    return $results;
  }

  public function deleteVoucher(UsersSalaryDetails $voucher): bool
  {
    $voucher->delete();
    $voucher->concept->delete();
    return true;
  }

  public function deleteEmployee(Employee $employee): bool
  {
    // Si el empleado está soft deleted, restaurarlo primero
    if ($employee->trashed()) {
      $employee->restore();
    }
    
    $employee->is_active = false;
    $employee->save();
    return true;
  }

  public function storeDocuments(Employee $employee, array $data)
  {
    $toUpdate = [];

    foreach (['photo', 'rif', 'residence_letter', 'cv'] as $key) {
      if (array_key_exists($key, $data)) {
        $toUpdate[$key] = $this->handleDocument(
          $data[$key],
          $employee[$key],
          "employees/$key"
        );
      }
    }

    if ($toUpdate) {
      $employee->update($toUpdate);
    }

    return true;
  }

  private function handleDocument(
    ?UploadedFile $file,
    ?string $currentPath,
    string $folder,
    string $disk = 'public'
  ): ?string {
    if (!$file) {
      return $currentPath;
    }

    if ($currentPath) {
      Storage::disk($disk)->delete($currentPath);
    }

    $extension = $file->getClientOriginalExtension() ?: $file->guessExtension();
    $filename = time() . '_' . uniqid() . ($extension ? '.' . $extension : '');

    return $file->storeAs($folder, $filename, $disk);
  }

  public function downloadDocument(string $path): Exception|StreamedResponse
  {
    if (!Storage::disk('public')->exists($path)) {
      throw new Exception('File not found');
    }

    return Storage::disk('public')->download($path, null, [
      'Content-Type' => 'application/pdf'
    ]);
  }

  public function reset2FA(Employee $employee): bool
  {
    $employee->user->update(['token_login' => null]);
    return true;
  }

  private const SALARIO_BASE = 40.00;
  private const BONO_ALIMENTACION = 40.00;

  /**
   * Deuda anterior para un mes dado: nuevo_saldo_deuda del mes anterior, o saldo actual del empleado si no hay registro previo.
   */
  private function getSaldoDeudaAnteriorParaMes(Employee $employee, int $year, int $month): float
  {
    $prevMonth = $month - 1;
    $prevYear = $year;
    if ($prevMonth < 1) {
      $prevMonth = 12;
      $prevYear = $year - 1;
    }
    $prev = EmployeePaymentCalculation::where('employee_id', $employee->id)
      ->where('year', $prevYear)
      ->where('month', $prevMonth)
      ->first();
    return $prev ? (float) $prev->nuevo_saldo_deuda : (float) ($employee->saldo_deuda ?? 0);
  }

  /**
   * Obtener datos para la vista de pagos: empleado, historial y opcionalmente distribución (conceptos) del mes.
   */
  public function getPayments(Employee $employee, array $data): array
  {
    $employee->refresh();
    $history = EmployeePaymentCalculation::where('employee_id', $employee->id)
      ->orderByDesc('year')
      ->orderByDesc('month')
      ->limit(24)
      ->get();

    $result = [
      'employee' => [
        'id' => $employee->id,
        'total_package_usd' => $employee->total_package_usd,
        'saldo_deuda' => $employee->saldo_deuda ?? 0,
      ],
      'history' => $history->map(fn (EmployeePaymentCalculation $row) => [
        'id' => $row->id,
        'year' => $row->year,
        'month' => $row->month,
        'fecha' => sprintf('%04d-%02d-01', $row->year, $row->month),
        'salario_base' => $row->salario_base,
        'bono_alimentacion' => $row->bono_alimentacion,
        'beneficio_salud' => $row->consumo_total_a_descontar,
        'consumo_farmacia_actual' => $row->consumo_farmacia_actual,
        'saldo_deuda_anterior' => $row->saldo_deuda_anterior,
        'incentivo_metas' => $row->incentivo_metas,
        'total_pagado_usd' => $row->total_pagado_usd,
        'total_pagado_ves' => $row->total_pagado_ves,
        'exchange_rate_ves' => $row->exchange_rate_ves,
        'created_at' => $row->created_at?->toIso8601String(),
      ])->values()->all(),
    ];

    $month = isset($data['month']) ? (int) $data['month'] : null;
    $year = isset($data['year']) ? (int) $data['year'] : null;
    $packageOverride = isset($data['total_package_usd']) ? (float) $data['total_package_usd'] : null;
    if ($month !== null && $year !== null) {
      $totalPackageUsd = $packageOverride ?? (float) ($employee->total_package_usd ?? 0);
      $consumo = $this->getHealthConsumption($employee->id, $year, $month);
      $saldoAnterior = $this->getSaldoDeudaAnteriorParaMes($employee, $year, $month);
      $calc = $this->computePaymentCalculation($totalPackageUsd, $consumo, $saldoAnterior);
      $result['distribution'] = [
        'month' => $month,
        'year' => $year,
        'total_package_usd' => $totalPackageUsd,
        'concepts' => [
          ['name' => 'Salario Básico Mensual', 'amount' => self::SALARIO_BASE, 'fixed' => true],
          ['name' => 'Cestaticket Socialista de Ley', 'amount' => self::BONO_ALIMENTACION, 'fixed' => true],
          ['name' => 'Asistencia Social de Salud (Art. 105 LOTTT)', 'amount' => $calc['consumo_total_a_descontar'], 'fixed' => false],
          ['name' => 'Gratificación Extraordinaria por Rendimiento', 'amount' => $calc['incentivo_metas'], 'fixed' => false],
        ],
        'consumo_farmacia_mes' => $consumo,
        'saldo_deuda_anterior' => $saldoAnterior,
        'total_a_cobrar' => round(self::SALARIO_BASE + self::BONO_ALIMENTACION + $calc['incentivo_metas'], 2),
      ];
    }

    return $result;
  }

  /**
   * Obtener consumo a crédito (Beneficio Salud Art. 105 LOTTT) del mes para el empleado.
   */
  public function getHealthConsumption(int $employeeId, int $year, int $month): float
  {
    $row = EmployeeHealthConsumption::where('employee_id', $employeeId)
      ->where('year', $year)
      ->where('month', $month)
      ->first();
    return $row ? (float) $row->amount : 0.0;
  }

  /**
   * Registrar o actualizar consumo salud para un mes (para que se traiga automáticamente al procesar nómina).
   */
  public function setHealthConsumption(Employee $employee, int $year, int $month, float $amount): void
  {
    EmployeeHealthConsumption::updateOrCreate(
      [
        'employee_id' => $employee->id,
        'year' => $year,
        'month' => $month,
      ],
      ['amount' => round($amount, 2)]
    );
  }

  /**
   * Ejecutar cálculo de nómina: consumo y deuda se traen automáticamente; se guarda tasa BCV y totales.
   */
  public function runPaymentCalculation(Employee $employee, array $data): array
  {
    $employee->refresh();
    $totalPackageUsd = (float) ($employee->total_package_usd ?? 0);
    if ($totalPackageUsd <= 0) {
      throw new \InvalidArgumentException('El empleado debe tener Paquete total (USD) definido en su perfil.');
    }

    $year = (int) ($data['year'] ?? now()->year);
    $month = (int) ($data['month'] ?? now()->month);

    $consumoFarmaciaActual = isset($data['consumo_farmacia_actual'])
      ? (float) $data['consumo_farmacia_actual']
      : $this->getHealthConsumption($employee->id, $year, $month);
    $saldoDeudaAnterior = (float) ($employee->saldo_deuda ?? 0);

    $calc = $this->computePaymentCalculation($totalPackageUsd, $consumoFarmaciaActual, $saldoDeudaAnterior);

    $totalPagadoUsd = round(self::SALARIO_BASE + self::BONO_ALIMENTACION + $calc['incentivo_metas'], 2);
    $bcv = ExchangeRate::where('currency_code', 'BS')->orderByDesc('updated_at')->first();
    $exchangeRateVes = $bcv ? (float) $bcv->rate : null;
    $totalPagadoVes = $exchangeRateVes !== null ? round($totalPagadoUsd * $exchangeRateVes, 2) : null;

    $record = EmployeePaymentCalculation::create([
      'employee_id' => $employee->id,
      'year' => $year,
      'month' => $month,
      'total_package_usd' => $totalPackageUsd,
      'salario_base' => self::SALARIO_BASE,
      'bono_alimentacion' => self::BONO_ALIMENTACION,
      'consumo_farmacia_actual' => $consumoFarmaciaActual,
      'saldo_deuda_anterior' => $saldoDeudaAnterior,
      'disponible_para_incentivo' => $calc['disponible_para_incentivo'],
      'consumo_total_a_descontar' => $calc['consumo_total_a_descontar'],
      'incentivo_metas' => $calc['incentivo_metas'],
      'nuevo_saldo_deuda' => $calc['nuevo_saldo_deuda'],
      'exchange_rate_ves' => $exchangeRateVes,
      'total_pagado_usd' => $totalPagadoUsd,
      'total_pagado_ves' => $totalPagadoVes,
    ]);

    $employee->update(['saldo_deuda' => $calc['nuevo_saldo_deuda']]);

    return [
      'calculation' => array_merge($calc, [
        'id' => $record->id,
        'year' => $year,
        'month' => $month,
        'consumo_farmacia_actual' => $consumoFarmaciaActual,
        'consumo_excedio_package' => $record->consumo_excedio_package,
        'exchange_rate_ves' => $exchangeRateVes,
        'total_pagado_usd' => $totalPagadoUsd,
        'total_pagado_ves' => $totalPagadoVes,
      ]),
      'employee' => [
        'id' => $employee->id,
        'saldo_deuda' => $employee->fresh()->saldo_deuda,
      ],
    ];
  }

  /**
   * Reglas de negocio:
   * disponible_para_incentivo = total_package_usd - salario_base - bono_alimentacion
   * consumo_total_a_descontar = consumo_farmacia_actual + saldo_deuda_anterior
   * Si disponible >= consumo_total -> incentivo = disponible - consumo_total, nuevo_saldo = 0
   * Si disponible < consumo_total -> incentivo = 0, nuevo_saldo = consumo_total - disponible
   */
  private function computePaymentCalculation(float $totalPackageUsd, float $consumoFarmaciaActual, float $saldoDeudaAnterior): array
  {
    $disponibleParaIncentivo = $totalPackageUsd - self::SALARIO_BASE - self::BONO_ALIMENTACION;
    $consumoTotalADescontar = $consumoFarmaciaActual + $saldoDeudaAnterior;

    if ($disponibleParaIncentivo >= $consumoTotalADescontar) {
      $incentivoMetas = $disponibleParaIncentivo - $consumoTotalADescontar;
      $nuevoSaldoDeuda = 0.0;
    } else {
      $incentivoMetas = 0.0;
      $nuevoSaldoDeuda = $consumoTotalADescontar - $disponibleParaIncentivo;
    }

    return [
      'salario_base' => self::SALARIO_BASE,
      'bono_alimentacion' => self::BONO_ALIMENTACION,
      'disponible_para_incentivo' => round($disponibleParaIncentivo, 2),
      'consumo_total_a_descontar' => round($consumoTotalADescontar, 2),
      'incentivo_metas' => round($incentivoMetas, 2),
      'nuevo_saldo_deuda' => round($nuevoSaldoDeuda, 2),
    ];
  }
}
