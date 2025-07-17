<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Services\Quotation\QuotationQueryService;
use App\Contracts\Client;
use App\Helpers\ApiResponse;

class OrderController extends Controller
{

    public function __construct(
        private QuotationQueryService $quotationQueryService,
        protected Client $client
    ) {
    }
    public function index(Request $request)
    {
        $query = $this->quotationQueryService->getFilteredQuery($request);
       $perPage = $request->input('itemsPerPage', 10);

        if ($perPage < 1) {
            $items = $query->get();
            return response()->json(['data' => $items, 'total' => $items->count()]);
        }
        $paginatedResult = $query->paginate($perPage);
        return response()->json(['data' => $paginatedResult->items(), 'total' => $paginatedResult->total()]);
    }

    public function  consultByIdentification(Request $request)
    {
        $buscarPorIdentificaion = $this->client->consultByIdentification($request->Identification);
        if (!$buscarPorIdentificaion) {
            return ApiResponse::success([
                    'found' => false,
                    'has_pending_credits' => false,
                    'identification_searched' => $request->Identification
                ],"the client not found", 200);
        }

        $pendingCredits = $buscarPorIdentificaion->pendingCredits;
        $hasPendingCredits = $pendingCredits->isNotEmpty();
        $totalPendingAmount = $pendingCredits->sum('pending_amount');
       /*  $pendingCreditsData = $pendingCredits->map(function($credit) {
        return [
            'id' => $credit->id,
            'order_id' => $credit->order_id,
            'amount' => $credit->amount,
            'pending_amount' => $credit->pending_amount,
        ];
    })->toArray();*/

        return ApiResponse::success([
                'found' => true,
                'client' => $buscarPorIdentificaion,
                'has_pending_credits' => $hasPendingCredits,
                //'pending_credits_data' => $pendingCreditsData,
                'total_pending_amount' => $totalPendingAmount
            ], "successfully", 200);
    }

    public function store(Request $request)
    {
        
    }
}
