<?php

namespace App\Http\Controllers\Api;

use App\Contracts\Doctor;
use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Requests\CreateDoctorRequest;
use App\Http\Requests\EditDoctorRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;

use App\Http\Resources\DoctorResource;

class DoctorController extends Controller
{
    public function __construct(
        protected Doctor $doctor
    ) {}


    public function create(CreateDoctorRequest $request): JsonResponse
    {
        $record = $this->doctor->create($request->data->all());
        return ApiResponse::success(new DoctorResource($record), "Doctor creado exitosamente", 200);
    }

    public function edit(EditDoctorRequest $request): JsonResponse
    {
        $buscarPorIdentificaion = $this->doctor->consultByIdentification($request->data->identification);
        if ($buscarPorIdentificaion) {
            if ($request->data->id != $buscarPorIdentificaion->id) {
                $errors = [
                    "identification" => ["No se puede actualizar porque la identificación ya está en uso"]
                ];
                return ApiResponse::error("No se puede actualizar porque la identificación ya está en uso", 400, $errors);
            }
        }

        $respuestaDB = $this->doctor->edit($request->data->id, $request->data->all());
        return ApiResponse::success(new DoctorResource($respuestaDB), "Doctor actualizado exitosamente", 200);
    }

    public function consultAll(): JsonResponse
    {
        $respuesDB = $this->doctor->consultAll();
        return ApiResponse::success(DoctorResource::collection($respuesDB), "Operación exitosa", 200);
    }

    public function consultById(Request $request)
    {
        $respuestaDB = $this->doctor->consultById($request->id);

        if (!$respuestaDB) {
            return ApiResponse::error("El doctor no fue encontrado", 404);
        }

        return ApiResponse::success($respuestaDB, "Operación exitosa", 200);
    }

    public function deleteById(Request $request): JsonResponse
    {
        $respuestaDB = $this->doctor->consultById($request->id);

        if (!$respuestaDB) {
            return ApiResponse::error("El doctor no fue encontrado", 404);
        }

        $this->doctor->deleteById($request->id);

        $validarEliminacio = $this->doctor->consultById($request->id);

        if ($validarEliminacio) {
            return ApiResponse::error("El doctor no pudo ser eliminado", 404);
        }

        return ApiResponse::success($validarEliminacio, "Doctor eliminado exitosamente", 200);
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

        if ($request->filled("fechaDesde_filtro") && $request->filled("fechaHasta_filtro")) {
            $filtros["fechaDesde_filtro"] = $request->fechaDesde_filtro;
            $filtros["fechaHasta_filtro"] = $request->fechaHasta_filtro;
        }

        if ($request->filled("orderBy") && $request->filled("sortBy")) {
            $filtros["orderBy"] = $request->orderBy;
            $filtros["sortBy"] = $request->sortBy;
        }

        $repuesta = $this->doctor->filtrar($filtros);

        return ApiResponse::success($repuesta, "ok", 200);
    }

    public function filtrarSinPaginar(Request $request)
    {
        $filtros = [];

        if ($request->filled("buscardor_filtro")) {
            $filtros["buscardor_filtro"] = $request->buscardor_filtro;
        }

        if ($request->filled("fechaDesde_filtro") && $request->filled("fechaHasta_filtro")) {
            $filtros["fechaDesde_filtro"] = $request->fechaDesde_filtro;
            $filtros["fechaHasta_filtro"] = $request->fechaHasta_filtro;
        }

        if ($request->filled("orderBy") && $request->filled("sortBy")) {
            $filtros["orderBy"] = $request->orderBy;
            $filtros["sortBy"] = $request->sortBy;
        }

        $repuesta = $this->doctor->filterWithoutPaginate($filtros);

        return ApiResponse::success($repuesta, "ok", 200);
    }

    public function exportarExcel(Request $request)
    {

        $filtros = [];

        if ($request->filled("buscardor_filtro")) {
            $filtros["buscardor_filtro"] = $request->buscardor_filtro;
        }

        if ($request->filled("fechaDesde_filtro") && $request->filled("fechaHasta_filtro")) {
            $filtros["fechaDesde_filtro"] = $request->fechaDesde_filtro;
            $filtros["fechaHasta_filtro"] = $request->fechaHasta_filtro;
        }

        if ($request->filled("orderBy") && $request->filled("sortBy")) {
            $filtros["orderBy"] = $request->orderBy;
            $filtros["sortBy"] = $request->sortBy;
        }

        $excel = $this->doctor->exportExcel($filtros);

        $fileName = 'doctors-' . now()->format('Y-m-d') . '.' . $request->formato;

        return Excel::download($excel, $fileName);
    }

    public function helpCheck(): JsonResponse
    {
        return ApiResponse::success(null, "ok", 200);
    }
}
