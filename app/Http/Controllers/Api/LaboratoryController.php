<?php

namespace App\Http\Controllers\Api;

use App\Contracts\Laboratory;
use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class LaboratoryController extends Controller
{
    //

    public function __construct(
        protected Laboratory $laboratory
    ) {}

    public function consultAll()
    {
        $respuestaDB = $this->laboratory->consultAll();
        return ApiResponse::success($respuestaDB, "ok", 200);
    }
}
