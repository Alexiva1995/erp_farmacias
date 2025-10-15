<?php

namespace App\Http\Controllers\Api;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Services\SocialBenefitServices;
use Illuminate\Http\Request;

class SocialBenefitController extends Controller
{
    public function __construct(protected SocialBenefitServices $socialBenefitServices)
    {
    }

    public function index(Request $request)
    {
        $data = [
            'search' => $request->search,
            'perPage' => $request->perPage,
        ];
        $result = $this->socialBenefitServices->index($data);

        return ApiResponse::success($result);
    }

    public function payment(Employee $employee, Request $request)
    {
        $data = $request->all();
        $result = $this->socialBenefitServices->payment($employee, $data);

        return ApiResponse::success(['status' => $result]);
    }

    public function getSettlementData(Employee $employee)
    {
        \Log::info('Controller', ['employee' => $employee]);
        $result = $this->socialBenefitServices->getSettlementData($employee);
        \Log::info('Controller', ['result' => $result]);

        return ApiResponse::success($result);
    }

    public function fire(Employee $employee, Request $request)
    {
        $data = $request->all();
        $result = $this->socialBenefitServices->fire($employee, $data);

        return ApiResponse::success(['status' => $result]);
    }
}
