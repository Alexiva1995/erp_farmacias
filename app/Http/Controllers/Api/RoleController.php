<?php

namespace App\Http\Controllers\Api;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Services\RoleServices;

class RoleController extends Controller
{
    public function __construct(private RoleServices $roleServices)
    {
    }

    public function list()
    {
        $results = $this->roleServices->list();
        return ApiResponse::success($results);
    }

}
