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
}
