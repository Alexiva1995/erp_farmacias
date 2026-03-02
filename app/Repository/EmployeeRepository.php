<?php

namespace App\Repository;

use App\Models\Employee;
use App\Models\EmployeeHealthConsumption;
use App\Models\EmployeePaymentCalculation;
use App\Models\ExchangeRate;
use App\Models\Order;
use App\Models\Client;
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
      ->with('resignation')
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
        $query->where(function ($q) use ($search) {
          $q->where('name', 'like', "%$search%")
            ->orWhere('last_name', 'like', "%$search%")
            ->orWhere('users.email', 'like', "%$search%")
            ->orWhere('identification', 'like', "%$search%");
        });
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
    
    // Si la columna 'fortnight' no existe en la BD, la quitamos del order by.
    // Usamos el id de mayor a menor como forma alternativa de ordenamiento secundario
    $history = EmployeePaymentCalculation::where('employee_id', $employee->id)
      ->orderByDesc('year')
      ->orderByDesc('month')
      ->orderByDesc('id')
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
        'fortnight' => $row->fortnight,
        'fecha' => sprintf('%04d-%02d-%02d', $row->year, $row->month, $row->fortnight == 1 ? 15 : 30),
        'salario_base' => $row->salario_base,
        'bono_alimentacion' => $row->bono_alimentacion,
        'beneficio_salud' => $row->beneficio_salud ?? $row->consumo_total_a_descontar,
        'consumo_farmacia_actual' => $row->consumo_farmacia_actual,
        'saldo_deuda_anterior' => $row->saldo_deuda_anterior,
        'incentivo_metas' => $row->incentivo_metas,
        'total_pagado_usd' => $row->total_pagado_usd,
        'total_pagado_ves' => $row->total_pagado_ves,
        'exchange_rate_ves' => $row->exchange_rate_ves,
        'created_at' => $row->created_at?->toIso8601String(),
      ])->values()->all(),
    ];

    $packageOverride = isset($data['total_package_usd']) ? (float) $data['total_package_usd'] : null;
    $totalPackageUsd = $packageOverride ?? (float) ($employee->total_package_usd ?? 0);
    $salarioBase = $this->getEmployeeSalarioBase($employee);
    $bonoAlimentacion = self::BONO_ALIMENTACION;
    
    // Obtener consumo del mes actual para visualización
    $consumoSaludReintegro = $this->getTotalConsumoFarmacia($employee);

    // Para la vista global, calculamos la capacidad mensual tras fijos y salud
    // No puede exceder el sobrante del paquete
    $maximoParaVariable = max(0, $totalPackageUsd - $salarioBase - $bonoAlimentacion);
    $saludLimitado = min($consumoSaludReintegro, $maximoParaVariable);
    $disponibleParaOtros = max(0, $maximoParaVariable - $saludLimitado);

    $result['distribution'] = [
      'month' => (int) now()->month,
      'year' => (int) now()->year,
      'total_package_usd' => $totalPackageUsd,
      'concepts' => [
        ['name' => 'Salario Básico Mensual', 'amount' => $salarioBase, 'fixed' => true],
        ['name' => 'Bono de Alimentación', 'amount' => $bonoAlimentacion, 'fixed' => true],
        ['name' => 'Asistencia Social de Salud (Art. 105 LOTTT)', 'amount' => $saludLimitado, 'fixed' => false], // Visualización mes actual
        ['name' => 'Bono Extraordinario de Rendimiento', 'amount' => $disponibleParaOtros, 'fixed' => false],
      ],
      'total_a_cobrar' => $totalPackageUsd,
    ];

    return $result;
  }

  private function getEmployeeSalarioBase(Employee $employee): float
  {
      $concept = SalaryConcept::where('name', 'Salario Básico Mensual')->first();
      if (!$concept || !$employee->user) return 40.00;

      $detail = $employee->user->salaries()->where('salary_concept_id', $concept->id)->first();
      return $detail ? (float) $detail->amount : 40.00;
  }

  /**
   * Calcular el consumo total del empleado en la farmacia como cliente (por cédula).
   * Suma todas las órdenes completadas del empleado como cliente, sin filtro de mes.
   */
  private function getTotalConsumoFarmacia(Employee $employee): float
  {
    $identification = $employee->identification;
    if (!$identification) return 0.0;

    $client = Client::where('identification', $identification)->first();
    if (!$client) return 0.0;

    return (float) Order::where('client_id', $client->id)
      ->whereMonth('order_date', now()->month)
      ->whereYear('order_date', now()->year)
      ->where(function ($q) {
        $q->where('status', 'Completed')
          ->orWhereNotNull('completed_at');
      })
      ->sum('total_amount_usd');
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
    $fortnight = isset($data['fortnight']) ? (int) $data['fortnight'] : (now()->day <= 15 ? 1 : 2);

    $consumoFarmaciaActual = isset($data['consumo_farmacia_actual'])
      ? (float) $data['consumo_farmacia_actual']
      : $this->getTotalConsumoFarmacia($employee);

    $saldoDeudaAnterior = $this->getSaldoDeudaAnteriorParaMes($employee, $year, $month);
    $salarioBase = $this->getEmployeeSalarioBase($employee);

    $calc = $this->computePaymentCalculation($totalPackageUsd, $consumoFarmaciaActual, $saldoDeudaAnterior, $salarioBase, $fortnight);

    $totalPagadoUsd = round($calc['total_quincena'], 2);
    $bcv = ExchangeRate::where('currency_code', 'BS')->orderByDesc('updated_at')->first();
    $exchangeRateVes = $bcv ? (float) $bcv->rate : null;
    $totalPagadoVes = $exchangeRateVes !== null ? round($totalPagadoUsd * $exchangeRateVes, 2) : 0;

    $record = EmployeePaymentCalculation::create([
      'employee_id' => $employee->id,
      'year' => $year,
      'month' => $month,
      'fortnight' => $fortnight,
      'total_package_usd' => $totalPackageUsd,
      'salario_base' => $calc['salario_base'],
      'bono_alimentacion' => $calc['bono_alimentacion'],
      'consumo_farmacia_actual' => $consumoFarmaciaActual,
      'saldo_deuda_anterior' => $saldoDeudaAnterior,
      'disponible_para_incentivo' => $calc['disponible_para_incentivo'] ?? 0,
      'consumo_total_a_descontar' => $calc['consumo_total_a_descontar'] ?? 0,
      'beneficio_salud' => $calc['salud_pagado'],
      'incentivo_metas' => $calc['incentivo_metas'],
      'nuevo_saldo_deuda' => $calc['nuevo_saldo_deuda'],
      'deduccion_ivss' => $calc['deduccion_ivss'],
      'deduccion_rpe' => $calc['deduccion_rpe'],
      'deduccion_faov' => $calc['deduccion_faov'],
      'exchange_rate_ves' => $exchangeRateVes,
      'total_pagado_usd' => $totalPagadoUsd,
      'total_pagado_ves' => $totalPagadoVes,
    ]);

    if ($fortnight == 2) {
      $employee->update(['saldo_deuda' => $calc['nuevo_saldo_deuda']]);
    }

    return [
      'calculation' => array_merge($calc, [
        'id' => $record->id,
        'year' => $year,
        'month' => $month,
        'fortnight' => $fortnight,
        'consumo_farmacia_actual' => $consumoFarmaciaActual,
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

  private function computePaymentCalculation(float $totalPackageUsd, float $consumoFarmaciaActual, float $saldoDeudaAnterior, float $salarioBase, int $fortnight): array
  {
    $salarioQuincena = round($salarioBase / 2, 2);
    
    // Deducciones legales (solo 1ra quincena)
    $deduccionIvss = round($salarioBase * 0.04, 2);
    $deduccionRpe = round($salarioBase * 0.005, 2);
    $deduccionFaov = round($salarioBase * 0.01, 2);

    $bonoAlimentacion = self::BONO_ALIMENTACION;
    
    // Solo en 2da quincena aplica la regla del excedente del paquete
    $disponibleParaIncentivo = $totalPackageUsd - $salarioBase - $bonoAlimentacion;
    $consumoTotalADescontar = $consumoFarmaciaActual + $saldoDeudaAnterior;

    $saludPagado = 0;
    $incentivoMetas = 0;
    $nuevoSaldoDeuda = $saldoDeudaAnterior; 

    if ($fortnight == 2) {
      $saludPagado = min($consumoTotalADescontar, max(0, $disponibleParaIncentivo));
      $restanteParaIncentivo = $disponibleParaIncentivo - $saludPagado;

      if ($restanteParaIncentivo > 0) {
        $incentivoMetas = $restanteParaIncentivo;
        $nuevoSaldoDeuda = 0.0;
      } else {
        $incentivoMetas = 0.0;
        $nuevoSaldoDeuda = $consumoTotalADescontar - max(0, $disponibleParaIncentivo);
      }
    }

    if ($fortnight == 1) {
      $totalQuincena = $salarioQuincena - $deduccionIvss - $deduccionRpe - $deduccionFaov;
    } else {
      $totalQuincena = $salarioQuincena + $bonoAlimentacion + $saludPagado + $incentivoMetas;
    }

    return [
      'salario_base' => $salarioQuincena,
      'bono_alimentacion' => $fortnight == 2 ? $bonoAlimentacion : 0,
      'disponible_para_incentivo' => $fortnight == 2 ? round($disponibleParaIncentivo, 2) : 0,
      'consumo_total_a_descontar' => $fortnight == 2 ? round($consumoTotalADescontar, 2) : 0,
      'salud_pagado' => round($saludPagado, 2),
      'incentivo_metas' => round($incentivoMetas, 2),
      'nuevo_saldo_deuda' => round($nuevoSaldoDeuda, 2),
      'deduccion_ivss' => $fortnight == 1 ? $deduccionIvss : 0,
      'deduccion_rpe' => $fortnight == 1 ? $deduccionRpe : 0,
      'deduccion_faov' => $fortnight == 1 ? $deduccionFaov : 0,
      'total_quincena' => round($totalQuincena, 2)
    ];
  }

  public function syncSalaryConcepts(Employee $employee, float $totalPackage): void
  {
    if (!$employee->user) return;

    // 1. Salario Básico Mensual 
    $baseConcept = SalaryConcept::firstOrCreate(
      ['name' => 'Salario Básico Mensual'],
      ['type' => 'salary', 'frequency' => 'monthly']
    );
    $existingBase = $employee->user->salaries()->where('salary_concept_id', $baseConcept->id)->first();
    $salarioBase = $existingBase ? (float) $existingBase->amount : 40.00;

    $employee->user->salaries()->updateOrCreate(
      ['salary_concept_id' => $baseConcept->id],
      ['amount' => $salarioBase]
    );

    // 2. Bono de Alimentación (Fixed $40)
    $foodConcept = SalaryConcept::firstOrCreate(
      ['name' => 'Bono de Alimentación'],
      ['type' => 'salary', 'frequency' => 'monthly']
    );
    $employee->user->salaries()->updateOrCreate(
      ['salary_concept_id' => $foodConcept->id],
      ['amount' => 40.00]
    );

    // 3. Asistencia Social de Salud (Variable, 0 por defecto)
    $healthConcept = SalaryConcept::firstOrCreate(
      ['name' => 'Asistencia Social de Salud (Art. 105 LOTTT)'],
      ['type' => 'salary', 'frequency' => 'monthly']
    );
    $employee->user->salaries()->updateOrCreate(
      ['salary_concept_id' => $healthConcept->id],
      ['amount' => 0.00]
    );

    // 4. Bono Extraordinario de Rendimiento (Variable, 0 por defecto)
    $bonusConcept = SalaryConcept::firstOrCreate(
      ['name' => 'Bono Extraordinario de Rendimiento'],
      ['type' => 'salary', 'frequency' => 'monthly']
    );
    $employee->user->salaries()->updateOrCreate(
      ['salary_concept_id' => $bonusConcept->id],
      ['amount' => 0.00]
    );

    // Limpieza de nombres antiguos y dinámicos obsoletos
    $dynamicNames = [
        'Performance Bonus', 
        'Salario Base',
        'Gratificación Extraordinaria', 
        'Gratificación Extraordinaria por Rendimiento'
    ];
    $dynamicIds = SalaryConcept::whereIn('name', $dynamicNames)->pluck('id');
    if ($dynamicIds->isNotEmpty()) {
      $employee->user->salaries()->whereIn('salary_concept_id', $dynamicIds)->delete();
    }
  }

  public function updatePayrollSettings(Employee $employee, array $data): array
  {
    $employee->update(array_filter($data));
    
    if (isset($data['total_package_usd'])) {
      $this->syncSalaryConcepts($employee, (float) $data['total_package_usd']);
    }

    // El método profile devuelve un modelo Employee, lo convertimos a array
    $profile = $this->profile($employee);
    return $profile ? $profile->toArray() : [];
  }
}

