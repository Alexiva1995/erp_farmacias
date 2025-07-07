<?php

namespace App\Http\Controllers\Api;

use App\Contracts\Company;
use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Requests\CreateCompanyRequest;
use App\Http\Requests\EditCompanyRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;

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

    public function filtrar(Request $request)
    {
        $filtros = [
            "itemsPerPage" => $request->itemsPerPage,
            "page"         => $request->page,
        ];

        if ($request->filled("buscardor_filtro")) {
            $filtros["buscardor_filtro"] = $request->buscardor_filtro;
        }

        if ($request->filled("tipo_empresa_filtro")) {
            $filtros["tipo_empresa_filtro"] = $request->tipo_empresa_filtro;
        }

        if ($request->filled("fechaDesde_filtro") && $request->filled("fechaHasta_filtro")) {
            $filtros["fechaDesde_filtro"] = $request->fechaDesde_filtro;
            $filtros["fechaHasta_filtro"] = $request->fechaHasta_filtro;
        }

        if ($request->filled("orderBy") && $request->filled("sortBy")) {
            $filtros["orderBy"] = $request->orderBy;
            $filtros["sortBy"] = $request->sortBy;
        }

        $repuesta = $this->company->filtrar($filtros);

        return ApiResponse::success($repuesta, "ok", 200);
    }

    public function filtrarSinPaginar(Request $request)
    {
        $filtros = [];

        if ($request->filled("buscardor_filtro")) {
            $filtros["buscardor_filtro"] = $request->buscardor_filtro;
        }

        if ($request->filled("tipo_empresa_filtro")) {
            $filtros["tipo_empresa_filtro"] = $request->tipo_empresa_filtro;
        }

        if ($request->filled("fechaDesde_filtro") && $request->filled("fechaHasta_filtro")) {
            $filtros["fechaDesde_filtro"] = $request->fechaDesde_filtro;
            $filtros["fechaHasta_filtro"] = $request->fechaHasta_filtro;
        }

        if ($request->filled("orderBy") && $request->filled("sortBy")) {
            $filtros["orderBy"] = $request->orderBy;
            $filtros["sortBy"] = $request->sortBy;
        }

        $repuesta = $this->company->filterWithoutPaginate($filtros);

        return ApiResponse::success($repuesta, "ok", 200);
    }

    public function exportarExcel(Request $request)
    {

        $filtros = [];

        if ($request->filled("buscardor_filtro")) {
            $filtros["buscardor_filtro"] = $request->buscardor_filtro;
        }

        if ($request->filled("tipo_empresa_filtro")) {
            $filtros["tipo_empresa_filtro"] = $request->tipo_empresa_filtro;
        }

        if ($request->filled("fechaDesde_filtro") && $request->filled("fechaHasta_filtro")) {
            $filtros["fechaDesde_filtro"] = $request->fechaDesde_filtro;
            $filtros["fechaHasta_filtro"] = $request->fechaHasta_filtro;
        }

        if ($request->filled("orderBy") && $request->filled("sortBy")) {
            $filtros["orderBy"] = $request->orderBy;
            $filtros["sortBy"] = $request->sortBy;
        }

        $excel = $this->company->exportExcel($filtros);

        $fileName = 'companies-' . now()->format('Y-m-d') . '.' . $request->formato;

        return Excel::download($excel, $fileName);
    }
}
