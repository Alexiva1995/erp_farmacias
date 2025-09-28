<?php

namespace App\Repository;

use App\Models\Employee;
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
      'amount' => 50,
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
    $role = $data['role'];
    $email = $data['email'];
    $role_id = Role::find($role)->id;

    $userData = [
      'username' => $username,
      'role_id' => $role_id,
      "email" => $email,
    ];

    if (!empty($data['password'])) {
      $userData['password_hash'] = Hash::make($data['password']);
    }

    $employee->user()->update($userData);

    unset($role);
    unset($email);
    unset($data['password']);

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
    $employee->delete();
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

    return $file->store($folder, $disk);
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
}
