<?php

namespace App\Http\Controllers\Api;

use App\Contracts\Company;
use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Requests\CreateCompanyRequest;
use App\Http\Requests\EditCompanyRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CompanyController extends Controller
{
    //

    public function __construct(
        protected Company $company
    ) {}


    public function create(CreateCompanyRequest $request): JsonResponse
    {
        $companyDb = $this->company->create($request->company->all());

        return ApiResponse::success($companyDb, "successfully", 200);
    }

    public function edit(EditCompanyRequest $request): JsonResponse
    {
        $respuestaDB = $this->company->edit($request->company->all());

        return ApiResponse::success($respuestaDB, "company successfully edited", 200);
    }


    public function consultAll(): JsonResponse
    {
        $respuesDB = $this->company->consultAll();
        return ApiResponse::success($respuesDB, "successfully", 200);
    }

    public function consultById(Request $request)
    {
        $respuestaDB = $this->company->consultById($request->id);

        if (!$respuestaDB) {
            return ApiResponse::error("the company not found", 404);
        }

        return ApiResponse::success($respuestaDB, "successfully", 200);
    }

    public function deleteById(Request $request): JsonResponse
    {
        $respuestaDB = $this->company->consultById($request->id);

        if (!$respuestaDB) {
            return ApiResponse::error("the company not found", 404);
        }

        $this->company->deleteById($request->id);

        $validarEliminacio = $this->company->consultById($request->id);

        if ($validarEliminacio) {
            return ApiResponse::error("the company not eliminated", 404);
        }

        return ApiResponse::success($validarEliminacio, "The company was successfully deleted", 200);
    }
}
