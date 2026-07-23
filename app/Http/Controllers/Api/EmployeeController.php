<?php

namespace App\Http\Controllers\Api;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreEmployeeDocumentsRequest;
use App\Http\Requests\StoreEmployeeRequest;
use App\Http\Requests\StoreEmployeeVoucherRequest;
use App\Http\Requests\UpdateEmployeeRequest;
use App\Http\Requests\UpdatePayrollSettingsRequest;
use App\Http\Requests\Employee\StorePaymentCalculationRequest;
use App\Http\Requests\Employee\SetHealthConsumptionRequest;
use App\Http\Resources\EmployeeResource;
use App\Http\Resources\VoucherResource;
use App\Models\Employee;
use App\Models\UsersSalaryDetails;
use App\Services\EmployeeServices;
use Illuminate\Http\Request;

class EmployeeController extends Controller
{
    public function __construct(private EmployeeServices $employeeServices)
    {
    }

    public function list(Request $request)
    {
        $data = $request->all();
        $results = $this->employeeServices->list($data);
        return response()->json([
            'data' => EmployeeResource::collection($results),
            'total' => $results->total()
        ]);
    }

    public function store(StoreEmployeeRequest $request)
    {
        $validated = $request->validated();
        $result = $this->employeeServices->store($validated);
        return ApiResponse::success(['status' => $result]);
    }

    public function fire(Employee $employee)
    {
        $result = $this->employeeServices->fire($employee);
        return ApiResponse::success(['status' => $result]);
    }

    public function update(Employee $employee, UpdateEmployeeRequest $request)
    {
        $validated = $request->validated();
        $result = $this->employeeServices->update($employee, $validated);
        return new EmployeeResource($employee->fresh());
    }

    public function profile(Employee $employee)
    {
        $data = $this->employeeServices->profile($employee);

        if (empty($data)) {
            return ApiResponse::error();
        }

        return new EmployeeResource($data);
    }

    public function storeVoucher(Employee $employee, StoreEmployeeVoucherRequest $request)
    {
        $validated = $request->validated();
        $this->employeeServices->storeVoucher($employee, $validated);
        return ApiResponse::success();
    }

    public function getVouchers(Employee $employee)
    {
        $results = $this->employeeServices->getVouchers($employee);
        return response()->json([
            'data' => VoucherResource::collection($results),
            'total' => $results->total()
        ]);
    }

    public function deleteVoucher(UsersSalaryDetails $voucher)
    {
        $results = $this->employeeServices->deleteVoucher($voucher);
        return ApiResponse::success(['status' => $results]);
    }

    public function deleteEmployee($employeeId)
    {
        $employee = Employee::withTrashed()->find($employeeId);
        
        if (!$employee) {
            return ApiResponse::error('Empleado no encontrado');
        }
        
        $results = $this->employeeServices->deleteEmployee($employee);
        return ApiResponse::success(['status' => $results]);
    }

    public function storeDocuments(Employee $employee, StoreEmployeeDocumentsRequest $request)
    {
        $data = $request->validated();
        // PHP no parsea multipart/form-data en PUT, por eso usamos POST.
        // Asegurar que los archivos se incluyan explícitamente:
        foreach (['photo', 'rif', 'residence_letter', 'cv'] as $key) {
            if ($request->hasFile($key)) {
                $data[$key] = $request->file($key);
            }
        }
        $results = $this->employeeServices->storeDocuments($employee, $data);
        
        // Recargar los datos actualizados del empleado
        $updatedEmployee = $this->employeeServices->profile($employee);
        
        return ApiResponse::success([
            'status' => $results,
            'data' => $updatedEmployee
        ]);
    }

    public function downloadDocument(Employee $employee, string $file)
    {
        try {
            $result = $this->employeeServices->downloadDocument($employee, $file);
            return $result;
        } catch (\Exception $e) {
            return ApiResponse::error($e->getMessage());
        }
    }

    public function reset2FA(Employee $employee)
    {
        $result = $this->employeeServices->reset2FA($employee);
        return ApiResponse::success(['status' => $result]);
    }

    public function getPayments(Employee $employee, Request $request)
    {
        $data = $request->only(['month', 'year', 'consumo_farmacia_actual']);
        $results = $this->employeeServices->getPayments($employee, $data);
        return ApiResponse::success($results);
    }

    public function storePaymentCalculation(Employee $employee, StorePaymentCalculationRequest $request)
    {
        $data = $request->validated();
        try {
            $results = $this->employeeServices->runPaymentCalculation($employee, $data);
            return ApiResponse::success($results);
        } catch (\InvalidArgumentException $e) {
            return ApiResponse::error($e->getMessage());
        }
    }

    public function setHealthConsumption(Employee $employee, SetHealthConsumptionRequest $request)
    {
        $data = $request->validated();
        $this->employeeServices->setHealthConsumption($employee, $data['year'], $data['month'], (float) $data['amount']);
        return ApiResponse::success(['message' => 'Consumo salud registrado.']);
    }

    public function updatePayrollSettings(Employee $employee, UpdatePayrollSettingsRequest $request)
    {
        $result = $this->employeeServices->updatePayrollSettings($employee, $request->validated());
        return ApiResponse::success(new EmployeeResource($employee->fresh()));
    }
}
