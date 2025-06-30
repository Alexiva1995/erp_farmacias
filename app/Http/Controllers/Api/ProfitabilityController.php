<?php

namespace App\Http\Controllers\api;

use App\Contracts\Profitability;
use App\Models\ProfitabilitySettings;
use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Requests\ProfitabilityCreateRequest;
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

    public function store(Request $request)
    {

        if ($this->profitability->consultById($request->product_id)) {

            $data = [
                'id'                       => $request->id,
                'product_id'               => $request->product_id,
                'profitability_percentage' => $request->profitability_percentage,
                'is_locked'                => $request->is_locked
            ];
            $this->profitability->editProduct($data);
        } else {
            $data = [
                'product_id'               => $request->product_id,
                'profitability_percentage' => $request->profitability_percentage,
                'is_locked'                => $request->is_locked
            ];

            $this->profitability->store($data);
        }

        return response()->json("Se ha enviado los datos con exito");
    }

    //Debe haber un buscador de los prodcutos que ya tienen un dato guardado

    /*public function editProduct(Request $request, $id)
    {
        $crear = [
            'product_id'               => $request->id_product,
            'profitability_percentage' => $request->profitability_percentage,
            'is_locked'                => $request->is_locked
        ];
        $this->profitability->editProduct($editar);

        return response()->json("Se ha actualizado el porcentaje");
    }*/

    public function edit(Request $request, $id)
    {
        $editar = [
            'default_profitability_percentage' => $request->default_profitability_percentage,
            'id'                 => $id
        ];
        $this->profitability->edit($editar);

        return response()->json("Se ha actualizado el porcentaje");
    }

    public function consultAll(Request $request)
    {
        return $this->profitability->consultAll();
    }
}
