<?php

namespace App\Http\Controllers\Api;

use App\Contracts\User;
use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UserController extends Controller
{


    public function __construct(
        protected User $user
    ) {}

    public function getAll(): JsonResponse
    {

        $respuestaConsulta = $this->user->getAll();

        return ApiResponse::success($respuestaConsulta, "ok");
    }
}
