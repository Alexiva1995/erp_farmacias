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
        //return dump($request->profitability);
        $this->profitability->editProduct($request->profitability->all());
        return response()->json("Se ha actualizado el porcentaje");
    }

    /*public function edit(Request $request, $id)
    {
        $editar = [
            'default_profitability_percentage' => $request->default_profitability_percentage,
            'id'                 => $id
        ];
        $this->profitability->edit($editar);

        return response()->json("Se ha actualizado el porcentaje");
    }*/

    public function store(Request $request)
    {
        $crear = [
            'default_profitability_percentage' => $request->default_profitability_percentage,
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
