<?php

namespace App\Http\Controllers\Api;

use App\Contracts\Profitability;
use App\Models\ProfitabilitySettings;
use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Requests\ProfitabilityProductCreateRequest;
use App\Http\Requests\ProfitabilityProductEditRequest;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProfitabilityController extends Controller
{
    //

    public function __construct(
        protected Profitability $profitability
    ) {}

    // Se deberia hacer una function del controller donce valide si existe un producto o no cuando se le da al candado

    public function storeProfitabilityProduct(ProfitabilityProductCreateRequest $request)
    {
        //dump($request->profitability);
        $this->profitability->storeProduct($request->profitability->all());
        return response()->json("Se ha guardado la rentabilidad del producto");
    }

    public function editProfitabilityProduct(ProfitabilityProductEditRequest $request)
    {
        $this->profitability->editProduct($request->profitability->all());
        return response()->json("Se ha actualizado el porcentaje");
    }

    public function store(Request $request)
    {
        $crear = [
            'default_profitability_percentage' => $request->default_profitability_percentage,
            'shipping_cost' => $request->shipping_cost,
            'packaging_cost' => $request->packaging_cost,
            'expense_margin' => $request->expense_margin,
            'profit_margin' => $request->profit_margin,
            'tax_usa' => $request->tax_usa,
        ];
        $this->profitability->store($crear);
        return response()->json("Se ha actualizado el porcentaje");
    }

    public function consultOne(Request $request)
    {
        return $this->profitability->consultOne();
    }

    public function getProduct($id)
    {

        if ($this->profitability->consultById($id)) {

            $data = [
                "message" => "Todo bien"
            ];
            return new JsonResponse($data, 200);
        } else {
            $data = [
                "message" => "Todo bien"
            ];
            return new JsonResponse($data, 404);
        }
    }
}
