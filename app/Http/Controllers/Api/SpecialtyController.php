<?php

namespace App\Http\Controllers\Api;

use App\Contracts\Specialty;
use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

class SpecialtyController extends Controller
{
    public function __construct(
        protected Specialty $specialty
    ) {}

    public function index(): JsonResponse
    {
        $records = $this->specialty->consultAll();
        return ApiResponse::success($records, "Especialidades obtenidas correctamente", 200);
    }
}
