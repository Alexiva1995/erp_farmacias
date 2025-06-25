<?php

namespace App\Http\Controllers\Api;

use App\Contracts\Client;
use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Requests\CreateClientRequest;
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

        return ApiResponse::success($clientDB, "llengan los datos", 200);
    }

    public function edit() {}

    public function consultAll(Request $request)
    {
        return $this->client->consultAll();
    }

    public function consultById() {}

    public function deleteById() {}
}
