<?php

namespace App\Http\Controllers\Api;

use App\Contracts\Client;
use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Requests\CreateClientRequest;
use App\Http\Requests\EditClientRequest;
use App\Http\Requests\UpdateCompanyClientFormRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;

class ClientController extends Controller
{
    //

    public function __construct(
        protected Client $client
    ) {
    }


    public function create(CreateClientRequest $request): JsonResponse
    {
        if ($request->client->identification_type == "J-") {
            if ($request->client->last_name != "" | $request->client->last_name != null) {
                $errors = [
                    "last_name" => ["Si el usuario es una entidad jurídica, el apellido no es necesario."]
                ];
                return ApiResponse::error("Si el usuario es una entidad jurídica, el apellido no es necesario.", 400, $errors);
            }
            if ($request->client->company_id != "" | $request->client->company_id != null) {
                $errors = [
                    "company_id" => ["Si el usuario es una entidad jurídica, la compañía no es necesaria."]
                ];
                return ApiResponse::error("Si el usuario es una entidad jurídica, la compañía no es necesaria.", 400, $errors);
            }
        }

        $respuestaDB = $this->client->create($request->client->all());

        return ApiResponse::success($respuestaDB, "Cliente creado exitosamente", 200);
    }

    public function edit(EditClientRequest $request): JsonResponse
    {
        $buscarPorIdentificaion = $this->client->consultByIdentification($request->client->identification);
        if ($buscarPorIdentificaion) {
            if ($request->client->id != $buscarPorIdentificaion->id) {
                $errors = [
                    "identification" => ["No se puede actualizar porque la cédula/RIF ya está en uso"]
                ];
                return ApiResponse::error("No se puede actualizar porque la cédula/RIF ya está en uso", 400, $errors);
            }
        }

        if ($request->client->identification_type == "J-") {
            if ($request->client->last_name != "" | $request->client->last_name != null) {
                $errors = [
                    "last_name" => ["Si el usuario es una entidad jurídica, el apellido no es necesario."]
                ];
                return ApiResponse::error("Si el usuario es una entidad jurídica, el apellido no es necesario.", 400, $errors);
            }
            if ($request->client->company_id != "" | $request->client->company_id != null) {
                $errors = [
                    "company_id" => ["Si el usuario es una entidad jurídica, la compañía no es necesaria."]
                ];
                return ApiResponse::error("Si el usuario es una entidad jurídica, la compañía no es necesaria.", 400, $errors);
            }
        }

        $respuestaDB = $this->client->edit($request->client->all());

        return ApiResponse::success($respuestaDB, "Cliente editado exitosamente", 200);
    }

    public function updateCompany(UpdateCompanyClientFormRequest $request): JsonResponse
    {
        $client_id = $request->data->client_id;
        $company_id = $request->data->company_id;
        $status = $request->data->status;
        $respuestaDB = $this->client->updateCompany($client_id, $company_id, $status);


        return ApiResponse::success($respuestaDB, "Cliente editado exitosamente", 200);
    }

    public function consultAll(Request $request)
    {
        $respuestaDB = $this->client->consultAll();
        return ApiResponse::success($respuestaDB, "Operación exitosa", 200);
    }

    public function consultById(Request $request)
    {
        $respuestaDB = $this->client->consultById($request->id);

        if (!$respuestaDB) {
            return ApiResponse::error("El cliente no fue encontrado", 404);
        }

        return ApiResponse::success($respuestaDB, "Operación exitosa", 200);
    }

    public function deleteById(Request $request)
    {
        $respuestaDB = $this->client->consultById($request->id);

        if (!$respuestaDB) {
            return ApiResponse::error("El cliente no fue encontrado", 404);
        }

        $this->client->deleteById($request->id);

        $validarEliminacio = $this->client->consultById($request->id);

        if ($validarEliminacio) {
            return ApiResponse::error("El cliente no fue eliminado", 404);
        }

        return ApiResponse::success($validarEliminacio, "Cliente eliminado exitosamente", 200);
    }

    public function filtrar(Request $request)
    {
        $filtros = [
            "itemsPerPage" => $request->itemsPerPage,
            "page" => $request->page,
        ];

        if ($request->filled("buscardor_filtro")) {
            $filtros["buscardor_filtro"] = $request->buscardor_filtro;
        }

        if ($request->filled("tipo_identificacion_filtro")) {
            $filtros["tipo_identificacion_filtro"] = $request->tipo_identificacion_filtro;
        }

        if ($request->filled("tipo") && $request->filled("tipo_identificacion_filtro") == false) {
            $filtros["tipo"] = $request->tipo;
        }

        if ($request->filled("fechaDesde_filtro") && $request->filled("fechaHasta_filtro")) {
            $filtros["fechaDesde_filtro"] = $request->fechaDesde_filtro;
            $filtros["fechaHasta_filtro"] = $request->fechaHasta_filtro;
        }

        if ($request->filled("company_id")) {
            $filtros["company_id"] = $request->company_id;
        }

        if ($request->filled("orderBy") && $request->filled("sortBy")) {
            $filtros["orderBy"] = $request->orderBy;
            $filtros["sortBy"] = $request->sortBy;
        }

        $repuesta = $this->client->filtrar($filtros);

        return ApiResponse::success($repuesta, "OK", 200);
    }

    public function filtrarSinPaginar(Request $request)
    {
        $filtros = [
            "itemsPerPage" => $request->itemsPerPage,
            "page" => $request->page,
        ];

        if ($request->filled("buscardor_filtro")) {
            $filtros["buscardor_filtro"] = $request->buscardor_filtro;
        }

        if ($request->filled("tipo_identificacion_filtro")) {
            $filtros["tipo_identificacion_filtro"] = $request->tipo_identificacion_filtro;
        }

        if ($request->filled("tipo") && $request->filled("tipo_identificacion_filtro") == false) {
            $filtros["tipo"] = $request->tipo;
        }

        if ($request->filled("fechaDesde_filtro") && $request->filled("fechaHasta_filtro")) {
            $filtros["fechaDesde_filtro"] = $request->fechaDesde_filtro;
            $filtros["fechaHasta_filtro"] = $request->fechaHasta_filtro;
        }

        if ($request->filled("company_id")) {
            $filtros["company_id"] = $request->company_id;
        }

        if ($request->filled("orderBy") && $request->filled("sortBy")) {
            $filtros["orderBy"] = $request->orderBy;
            $filtros["sortBy"] = $request->sortBy;
        }

        $repuesta = $this->client->filterWithoutPaginate($filtros);

        return ApiResponse::success($repuesta, "OK", 200);
    }

    public function exportarExcel(Request $request)
    {
        $filtros = [];

        if ($request->filled("buscardor_filtro")) {
            $filtros["buscardor_filtro"] = $request->buscardor_filtro;
        }

        if ($request->filled("tipo_identificacion_filtro")) {
            $filtros["tipo_identificacion_filtro"] = $request->tipo_identificacion_filtro;
        }

        if ($request->filled("tipo") && $request->filled("tipo_identificacion_filtro") == false) {
            $filtros["tipo"] = $request->tipo;
        }

        if ($request->filled("fechaDesde_filtro") && $request->filled("fechaHasta_filtro")) {
            $filtros["fechaDesde_filtro"] = $request->fechaDesde_filtro;
            $filtros["fechaHasta_filtro"] = $request->fechaHasta_filtro;
        }

        if ($request->filled("company_id")) {
            $filtros["company_id"] = $request->company_id;
        }

        $excel = $this->client->exportExcel($filtros);

        $fileName = 'clientes-' . now()->format('Y-m-d') . '.' . $request->formato;

        return Excel::download($excel, $fileName);
    }
}
