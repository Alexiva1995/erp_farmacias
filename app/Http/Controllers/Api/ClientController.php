<?php

namespace App\Http\Controllers\Api;

use App\Contracts\Client;
use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Requests\CreateClientRequest;
use App\Http\Requests\EditClientRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ClientController extends Controller
{
    //

    public function __construct(
        protected Client $client
    ) {}

    public function create(CreateClientRequest $request): JsonResponse
    {

        if ($request->client->identification_type == "J-") {
            if ($request->client->last_name != "" | $request->client->last_name != null) {
                $errors = [
                    "last_name" => ["If the user is a legal entity, the last name is not necessary."]
                ];
                return ApiResponse::error("If the user is a legal entity, the last name is not necessary.", 400, $errors);
            }
            if ($request->client->company_id != "" | $request->client->company_id != null) {
                $errors = [
                    "company_id" => ["If the user is a legal entity, the company is not necessary."]
                ];
                return ApiResponse::error("If the user is a legal entity, the company is not necessary.", 400, $errors);
            }
        }

        $respuestaDB = $this->client->create($request->client->all());

        return ApiResponse::success($respuestaDB, "client created successfully", 200);
    }

    public function edit(EditClientRequest $request): JsonResponse
    {


        $buscarPorIdentificaion = $this->client->consultByIdentification($request->client->identification);
        if ($buscarPorIdentificaion) {
            if ($request->client->id != $buscarPorIdentificaion->id) {
                $errors = [
                    "identification" => ["Cannot update because the ID is already in use"]
                ];
                return ApiResponse::error("Cannot update because the ID is already in use", 400, $errors);
            }
        }

        if ($request->client->identification_type == "J-") {
            if ($request->client->last_name != "" | $request->client->last_name != null) {
                $errors = [
                    "last_name" => ["If the user is a legal entity, the last name is not necessary."]
                ];
                return ApiResponse::error("If the user is a legal entity, the last name is not necessary.", 400, $errors);
            }
            if ($request->client->company_id != "" | $request->client->company_id != null) {
                $errors = [
                    "company_id" => ["If the user is a legal entity, the company is not necessary."]
                ];
                return ApiResponse::error("If the user is a legal entity, the company is not necessary.", 400, $errors);
            }
        }

        $respuestaDB = $this->client->edit($request->client->all());

        return ApiResponse::success($respuestaDB, "client successfully edited", 200);
    }

    public function consultAll(Request $request)
    {
        $respuestaDB = $this->client->consultAll();
        return ApiResponse::success($respuestaDB, "successfully", 200);
    }

    public function consultById(Request $request)
    {
        $respuestaDB = $this->client->consultById($request->id);

        if (!$respuestaDB) {
            return ApiResponse::error("the client not found", 404);
        }

        return ApiResponse::success($respuestaDB, "successfully", 200);
    }

    public function deleteById(Request $request)
    {
        $respuestaDB = $this->client->consultById($request->id);

        if (!$respuestaDB) {
            return ApiResponse::error("the client not found", 404);
        }

        $this->client->deleteById($request->id);

        $validarEliminacio = $this->client->consultById($request->id);

        if ($validarEliminacio) {
            return ApiResponse::error("the client not eliminated", 404);
        }

        return ApiResponse::success($validarEliminacio, "The client was successfully deleted", 200);
    }

    public function filrar(Request $request)
    {
        $filtros = [
            "itemsPerPage" => $request->itemsPerPage,
            "page"         => $request->page,
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

        // tablas
        if ($request->filled("orderBy") && $request->filled("sortBy")) {
            $filtros["orderBy"] = $request->orderBy;
            $filtros["sortBy"] = $request->sortBy;
        }


        $repuesta = $this->client->filtrar($filtros);

        return ApiResponse::success($repuesta, "ok", 200);
    }
}
