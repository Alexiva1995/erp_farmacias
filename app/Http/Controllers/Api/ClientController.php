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

        $clientDB = $this->client->create($request->client);

        return ApiResponse::success($clientDB, "client created successfully", 200);
    }

    public function edit(EditClientRequest $request): JsonResponse
    {

        $clientUpdateDB = $this->client->edit($request->client);

        return ApiResponse::success($clientUpdateDB, "client successfully edited", 200);
    }

    public function consultAll(Request $request)
    {
        return $this->client->consultAll();
    }

    public function consultById() {}

    public function deleteById() {}
}
