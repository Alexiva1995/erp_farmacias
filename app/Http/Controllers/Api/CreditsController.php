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

        if ($perPage < 1) {
            $items = $query->get();
            return response()->json(['data' => $items, 'total' => $items->count()]);
        }
        $paginatedResult = $query->paginate($perPage);
        return response()->json(['data' => $paginatedResult->items(), 'total' => $paginatedResult->total()]);
    }

    public function updateCreditStatus(UpdateCreditStatusRequest $request, Credit $credit)
    {
        try {
            $updatedCredit = $this->creditsActionService->updateStatus($credit, $request->validated('status'));
            return ApiResponse::success([
                'credit' => $updatedCredit,
            ], 'Estado del crédito actualizado correctamente.');
            
        } catch (\Exception $e) {
            return ApiResponse::error('Error al actualizar el estado del crédito: ' . $e->getMessage(), 500);
        }
    }
}
