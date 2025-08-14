<?php

namespace App\Http\Controllers\Api;

use App\Contracts\Product;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class SupplierIaAssistantReportController extends Controller
{
    //

    public function __construct(
        protected Product $product
    ) {}

    public function filtrarPaginate(Request $request) {}
}
