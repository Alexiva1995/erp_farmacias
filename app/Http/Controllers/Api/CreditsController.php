<?php

namespace App\Http\Controllers\Api;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Requests\Credits\CompleteCreditRequest;
use App\Http\Requests\Credits\CreditIdsRequest;
use App\Http\Requests\Credits\GetPaymentHistoryRequest;
use App\Http\Requests\Credits\IndexCreditPaymentRequest;
use App\Http\Requests\Credits\IndexCreditRequest;
use App\Http\Requests\Credits\UpdateCreditStatusRequest;
use App\Http\Requests\Credits\VoidCreditPaymentRequest;
use App\Http\Resources\Finances\CreditDetailResource;
use App\Http\Resources\Finances\CreditPaymentResource;
use App\Http\Resources\Finances\CreditResource;
use App\Models\Credit;
use App\Models\CreditPayment;
use App\Models\User;
use App\Services\Credits\CreditsActionService;
use App\Services\Credits\CreditsQueryService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class CreditsController extends Controller
{
    public function __construct(
        private CreditsQueryService $creditsQueryService,
        private CreditsActionService $creditsActionService,
    ) {
    }

    public function index(IndexCreditRequest $request)
    {
        $query = $this->creditsQueryService->getFilteredQuery($request);

        $perPage = (int) $request->input('itemsPerPage', 10);
        $paginatedResult = $query->paginate($perPage)->withQueryString();
        $kpis = $this->creditsQueryService->getKpis();

        return response()->json([
            'data' => CreditResource::collection($paginatedResult->getCollection()),
            'total' => $paginatedResult->total(),
            'kpis' => $kpis,
        ]);
    }

    public function destroy(CreditIdsRequest $request)
    {
        if (Auth::id() && User::find(Auth::id())?->role_id !== 1) {
            return ApiResponse::error('No autorizado. Solo administradores pueden eliminar créditos.', 403);
        }

        try {
            $this->creditsActionService->delete(
                $request->input('credit_ids'),
                $request->input('reason')
            );
            return ApiResponse::success(null, 'Crédito(s) eliminado(s) correctamente.');
        } catch (\RuntimeException $e) {
            return ApiResponse::error($e->getMessage(), 422);
        } catch (\Exception $e) {
            Log::error('Error al eliminar crédito:', ['error' => $e->getMessage()]);
            return ApiResponse::error('No se pudo eliminar el crédito: ' . $e->getMessage(), 500);
        }
    }

    public function updateCreditStatus(UpdateCreditStatusRequest $request, Credit $credit)
    {
        $validated = $request->validated();

        $success = $this->creditsActionService->updateStatus(
            $validated['ids'],
            $validated['status']
        );

        if ($success) {
            return ApiResponse::success(null, 'El estado de los créditos ha sido actualizado con éxito.');
        }

        return ApiResponse::error('Error al actualizar el estado de los créditos.', 500);
    }

    public function completeCredits(CompleteCreditRequest $request)
    {
        try {
            $this->creditsActionService->complete($request);
            return ApiResponse::success(null, 'Pago completado exitosamente.');
        } catch (\RuntimeException $e) {
            return ApiResponse::error($e->getMessage(), 422);
        } catch (\Exception $e) {
            Log::error('Error al completar el pago:', ['error' => $e->getMessage()]);
            return ApiResponse::error('No se pudo completar la orden: ' . $e->getMessage(), 500);
        }
    }

    public function showDetails(CreditIdsRequest $request)
    {
        $credits = $this->creditsQueryService->getCreditDetails($request->input('credit_ids'));

        return ApiResponse::success(CreditDetailResource::collection($credits));
    }

    public function getPaymentHistory(GetPaymentHistoryRequest $request)
    {
        $payments = $this->creditsQueryService->getClientPaymentHistory((int) $request->input('client_id'));

        return ApiResponse::success($payments);
    }

    public function destroyPayment(VoidCreditPaymentRequest $request, CreditPayment $payment)
    {
        if (Auth::id() && User::find(Auth::id())?->role_id !== 1) {
            return ApiResponse::error('No autorizado. Solo administradores pueden anular abonos.', 403);
        }

        try {
            $this->creditsActionService->deletePayment($payment, $request->input('reason'));
            return ApiResponse::success(null, 'Abono de crédito anulado y saldo revertido correctamente.');
        } catch (\RuntimeException $e) {
            return ApiResponse::error($e->getMessage(), 422);
        } catch (\Exception $e) {
            Log::error('Error al anular abono de crédito:', ['error' => $e->getMessage()]);
            return ApiResponse::error('No se pudo anular el abono: ' . $e->getMessage(), 500);
        }
    }

    public function payments(IndexCreditPaymentRequest $request)
    {
        $paginated = $this->creditsQueryService->getPaymentsPaginated($request);

        return response()->json([
            'data' => CreditPaymentResource::collection($paginated->items()),
            'total' => $paginated->total(),
        ]);
    }
}
