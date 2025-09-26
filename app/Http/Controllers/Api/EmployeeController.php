<?php

namespace App\Http\Controllers\Api;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreEmployeeDocumentsRequest;
use App\Http\Requests\StoreEmployeeRequest;
use App\Http\Requests\StoreEmployeeVoucherRequest;
use App\Http\Requests\UpdateEmployeeRequest;
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
        return $this->employeeServices->list($data);
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
        return ApiResponse::success(['status' => $result]);
    }

    public function profile(Employee $employee)
    {
        $data = $this->employeeServices->profile($employee);

        if (empty($data)) {
            return ApiResponse::error();
        }

        return ApiResponse::success($data);
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
        return ApiResponse::success(['data' => $results->items(), 'total' => $results->total()]);
    }

    public function deleteVoucher(UsersSalaryDetails $voucher)
    {
        $results = $this->employeeServices->deleteVoucher($voucher);
        return ApiResponse::success(['status' => $results]);
    }

    public function deleteEmployee(Employee $employee)
    {
        $results = $this->employeeServices->deleteEmployee($employee);
        return ApiResponse::success(['status' => $results]);
    }

    public function storeDocuments(Employee $employee, StoreEmployeeDocumentsRequest $request)
    {
        $data = $request->validated();
        $results = $this->employeeServices->storeDocuments($employee, $data);
        return ApiResponse::success(['status' => $results]);
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

    public function payments(Request $request)
    {
        $data = $request->all();
        $results = $this->employeeServices->payments($data);
        return ApiResponse::success($results);

    }
}
