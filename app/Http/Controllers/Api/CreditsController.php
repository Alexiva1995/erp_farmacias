<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Services\Credits\CreditsQueryService;
use App\Services\Credits\CreditsActionService;
use App\Models\Credit;
use App\Helpers\ApiResponse;
use App\Http\Requests\Credits\UpdateCreditStatusRequest;
use Illuminate\Support\Facades\Log; 

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
        $paginatedResult = $query->paginate($perPage)->withQueryString();

        $credits = $paginatedResult->getCollection()->map(function ($credit) {
            if ($credit->credit_ids) {
                $credit->credit_ids = explode(',', $credit->credit_ids);
            } else {
                $credit->credit_ids = [];
            }
            return $credit;
        });

        return response()->json([
            'data' => $credits,
            'total' => $paginatedResult->total(),
        ]);
    }

    public function updateCreditStatus(UpdateCreditStatusRequest $request, Credit $credit)
    {
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


    public function completeCredits(Request $request)
    {
        try {
            $this->creditsActionService->complete($request);
        } catch (\Exception $e) {
            Log::error('Error al completar el pago:', ['error' => $e->getMessage()]);
            return ApiResponse::error('No se pudo completar la orden: ' . $e->getMessage(), 500);
        }
    }
}
