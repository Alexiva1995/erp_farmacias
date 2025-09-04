<?php

namespace App\Http\Controllers;

use App\Contracts\Expenses;
use App\Helpers\ApiResponse;
use Illuminate\Http\Request;

class ExpensesController extends Controller
{
    //TODO: 

    public function __construct(
        protected Expenses $expenses
    ) {}


    public function getAll()
    {

        return ApiResponse::success(null, "ok");
    }
}
