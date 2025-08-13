<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Services\Credits\CreditsQueryService;
use App\Services\Credits\CreditsActionService;
use App\Models\Credit;
use App\Helpers\ApiResponse;
use App\Http\Requests\Credits\UpdateCreditStatusRequest; 

class CreditsController extends Controller
{
    public function __construct(
        private CreditsQueryService $creditsQueryService,
        private CreditsActionService $creditsActionService,
    ) {
    }

    public function index(Request $request)
    {
       $query = $this->creditsQueryService->getFilteredQuery($request);
    
       $perPage = $request->input('itemsPerPage', 10);
     //  $paginatedCredits = $query->paginate($perPage)->withQueryString();

        $paginatedResult = $query->paginate($perPage)->withQueryString();
 // 3. Mapear los elementos paginados para convertir la cadena de IDs en un arreglo.
        $credits = $paginatedResult->getCollection()->map(function ($credit) {
            if ($credit->credit_ids) {
                $credit->credit_ids = explode(',', $credit->credit_ids);
            } else {
                $credit->credit_ids = [];
            }
            return $credit;
        });

        // 4. Devolver la respuesta con los datos transformados y la información de paginación.
        return response()->json([
            'data' => $credits,
            'total' => $paginatedResult->total(),
        ]);
     /*  if ($perPage < 1) {
            $items = $query->get();
            return response()->json(['data' => $items, 'total' => $items->count()]);
        }
        $paginatedResult = $query->paginate($perPage);
        return response()->json(['data' => $paginatedResult->items(), 'total' => $paginatedResult->total()]);*/
    }

    public function updateCreditStatus(UpdateCreditStatusRequest $request, Credit $credit)
    {
        /*try {
            $updatedCredit = $this->creditsActionService->updateStatus($credit, $request->validated('status'));
            return ApiResponse::success([
                'credit' => $updatedCredit,
            ], 'Estado del crédito actualizado correctamente.');
            
        } catch (\Exception $e) {
            return ApiResponse::error('Error al actualizar el estado del crédito: ' . $e->getMessage(), 500);
        }*/

         $validated = $request->validated();

        $success = $this->creditsActionService->updateStatus(
            $validated['ids'],
            $validated['status']
        );

        if ($success) {
            return response()->json([
                'message' => 'El estado de los créditos ha sido actualizado con éxito.',
            ]);
        }
        
        return response()->json([
            'message' => 'Error al actualizar el estado de los créditos.',
        ], 500);

    }
}
