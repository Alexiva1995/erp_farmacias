<?php

namespace App\Http\Controllers\Api;

use App\Contracts\Company;
use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CompanyController extends Controller
{
    //

    public function __construct(
        protected Company $company
    ) {}


    public function consultAll(): JsonResponse
    {
        $respuesDB = $this->company->consultAll();
        return ApiResponse::success($respuesDB, "successfully", 200);
    }
}
