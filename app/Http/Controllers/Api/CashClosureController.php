<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Services\CashClosure\CashClosureActionService;
use App\Services\CashClosure\CashClosureQueryService;
use Barryvdh\DomPDF\Facade\Pdf;
use Maatwebsite\Excel\Facades\Excel;
use App\Exports\CashClosureExport;
use App\Http\Requests\CashClosure\CloseCashClosureRequest;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Pagination\LengthAwarePaginator;
use App\Models\Order;

class CashClosureController extends Controller
{

    public function __construct(
        private CashClosureActionService $cashClosureActionService,
        private CashClosureQueryService $cashClosureQueryService,
    ) {
    }

    public function getCashClosure(Request $request)
    {
        $query = $this->cashClosureActionService->allCashClosing($request);
        return $query;
    }

    public function getClosingHistory(Request $request)
    {
        $query = $this->cashClosureQueryService->getFilteredQuery($request);
        $perPage = $request->input('itemsPerPage', 10);

        if ($perPage < 1) {
            $items = $query->get();
            return response()->json(['data' => $items, 'total' => $items->count()]);
        }
        $paginatedResult = $query->paginate($perPage);
        return response()->json(['data' => $paginatedResult->items(), 'total' => $paginatedResult->total()]);
    }


    public function pdf($html)
    {

        $html = str_replace(
            ['á', 'é', 'í', 'ó', 'ú', 'ñ', 'Á', 'É', 'Í', 'Ó', 'Ú', 'Ñ', '°'],
            [
                '&aacute;',
                '&eacute;',
                '&iacute;',
                '&oacute;',
                '&uacute;',
                '&ntilde;',
                '&Aacute;',
                '&Eacute;',
                '&Iacute;',
                '&Oacute;',
                '&Uacute;',
                '&Ntilde;',
                '&deg;'
            ],
            $html
        );
        $pdf = Pdf::loadHtml($html);
        $pdf->setOptions([
            'encoding' => 'UTF-8'
        ]);
        return $pdf;
    }

    public function generate(Request $request)
    {
        $request->validate([
            'html' => 'required|string',
            'filename' => 'required|string'
        ]);
        $pdf = $this->pdf($request->input('html'));
        return $pdf->download($request->input('filename'));
    }

    public function closeCash(CloseCashClosureRequest $request)
    {
        return $this->cashClosureActionService->closeCashClosing($request);
    }

    public function getCashClosureOrders(Request $request)
    {
        try {
            $query = $this->cashClosureQueryService->getFilteredQueryOrder($request);
        } catch (ModelNotFoundException $e) {
            return response()->json(['data' => [], 'total' => 0]);
        }

        $perPage = $request->input('itemsPerPage', 10);

        if ($perPage < 1) {
            $items = $query->get();
            return response()->json(['data' => $items, 'total' => $items->count()]);
        }
        $paginatedResult = $query->paginate($perPage);
        return response()->json(['data' => $paginatedResult->items(), 'total' => $paginatedResult->total()]);
    }

    public function getSummarysales()
    {
        $summaryData = $this->cashClosureActionService->getMonthlySalesSummaryData();
        return response()->json($summaryData);
    }

    public function getDailyCashTable(Request $request)
    {
        $query = $this->cashClosureQueryService->getFilteredQueryDaily($request);
        $perPage = $request->input('itemsPerPage', 10);

        if ($perPage < 1) {
            $items = $query->get();
            return response()->json(['data' => $items, 'total' => $items->count()]);
        }
        
        $paginatedResult = $query->paginate($perPage);
        return response()->json(['data' => $paginatedResult->items(), 'total' => $paginatedResult->total()]);
    }

    public function getMonthlyCashTable(Request $request)
    {
        $query = $this->cashClosureQueryService->getFilteredQueryMonthly($request);
        $perPage = $request->input('itemsPerPage', 10);
        $perPage = $request->input('itemsPerPage', 10);
        $page = $request->input('page', 1);
        $offset = ($page - 1) * $perPage;
        $itemsForCurrentPage = $query->slice($offset, $perPage)->values();
        $paginatedResult = new LengthAwarePaginator(
            $itemsForCurrentPage,
            $query->count(),
            $perPage,
            $page,
            ['path' => $request->url(), 'query' => $request->query()]
        );
        return response()->json([
            'data' => $paginatedResult->items(),
            'total' => $paginatedResult->total()
        ]);
    }
    public function getSellerCashTable(Request $request)
    {
       
        $query = $this->cashClosureQueryService->getFilteredQuerySellerCash($request);
        $perPage = $request->input('itemsPerPage', 10);
        
        if ($perPage < 1) {
            $items = $query->get();
            return response()->json(['data' => $items, 'total' => $items->count()]);
        }
        $paginatedResult = $query->paginate($perPage);
        return response()->json(['data' => $paginatedResult->items(), 'total' => $paginatedResult->total()]);
    }

    public function getmonthlyCashclosing(Request $request)
    {
        $dailyClosureIds = $request->input('closingMonthlyIds', []);
        $cashClosings = $this->cashClosureActionService->getCashClosingsForMonthlySummary($dailyClosureIds);
        return response()->json([
            'data' => $cashClosings
        ]);
    }

    public function downloadReport(Request $request)
    {
        $filename = $request->input('filename') . now()->format('Y_m_d_His') . '.pdf';
        $pdf = $this->pdf($request->input('html_content'));
        return $pdf->download($filename);
    }

    public function printdReport(Request $request)
    {
        $filename = $request->input('filename') . now()->format('Y_m_d_His') . '.pdf';
        $pdf = $this->pdf($request->input('html_content'));
        return $pdf->stream($filename);
    }

    public function getmonthlyCashclosingAllSellers(Request $request)
    {
        $dailyClosureIds = $request->input('closingMonthlyIds', []);
        $cashClosings = $this->cashClosureActionService->getCashClosingsAllSellers($dailyClosureIds);
        return response()->json([
            'data' => $cashClosings
        ]);
    }

    public function getSellersWithClosures()
    {
        $sellers = $this->cashClosureQueryService->getSellersWithClosures();
        return response()->json($sellers);
    }

    public function confirmReference(Request $request)
    {
        $request->validate([
            'order_id' => 'required|exists:orders,id',
            'reference_code' => 'required',
        ]);

        $order = Order::findOrFail($request->order_id);
        // Obtenemos los métodos de pago crudos (sin filtros del accessor)
        $rawPaymentMethods = $order->getRawOriginal('payment_methods');
        $paymentMethods = is_string($rawPaymentMethods) ? json_decode($rawPaymentMethods, true) : ($rawPaymentMethods ?? []);
        
        $updated = false;
        
        // Normalizamos el código buscado (Mayúsculas y sin espacios)
        $searchRef = strtoupper(preg_replace('/\s+/', '', (string)$request->reference_code));

        foreach ($paymentMethods as &$method) {
            // Normalizamos el código actual del mismo modo para una comparación infalible
            $currentRef = isset($method['reference']) ? strtoupper(preg_replace('/\s+/', '', (string)$method['reference'])) : null;
            
            if ($currentRef === $searchRef) {
                $method['is_confirmed'] = true;
                $updated = true;
                break;
            }
        }

        if ($updated) {
            // Persistencia de "Hierro": Usamos DB::table para saltar accessors y cache de Eloquent
            \Illuminate\Support\Facades\DB::table('orders')
                ->where('id', $order->id)
                ->update(['payment_methods' => json_encode($paymentMethods)]);
            
            \Illuminate\Support\Facades\Log::info("Referencia confirmada y ESCRITA EN DISCO: {$searchRef} en Orden #{$order->id}");

            return response()->json([
                'status' => 'success',
                'message' => 'Referencia confirmada con éxito'
            ]);
        }

        \Illuminate\Support\Facades\Log::warning("No se encontró coincidencia para: {$searchRef} en los métodos de la Orden #{$order->id}");

        return response()->json([
            'status' => 'error',
            'message' => 'Referencia no encontrada'
        ], 404);
    }

    public function updateBlindAmounts(Request $request)
    {
        // Solo administradores (role_id = 1)
        if ($request->user()->role_id !== 1) {
            return response()->json(['message' => 'Acceso denegado. Solo administradores pueden realizar esta acción.'], 403);
        }

        $request->validate([
            'id' => 'required|exists:cash_closing,id',
            'declared_cop' => 'nullable|numeric',
            'declared_usd' => 'nullable|numeric',
            'declared_credit' => 'nullable|numeric',
            'declared_bs_mobile' => 'nullable|numeric',
            'declared_bs_card' => 'nullable|numeric',
        ]);

        $cashClosing = \App\Models\CashClosing::findOrFail($request->id);

        $decCop = (float) ($request->declared_cop ?? 0);
        $decUsd = (float) ($request->declared_usd ?? 0);
        $decCredit = (float) ($request->declared_credit ?? 0);
        $decBsMobile = (float) ($request->declared_bs_mobile ?? 0);
        $decBsCard = (float) ($request->declared_bs_card ?? 0);

        // Recalcular discrepancias con el sistema actual
        $sysCop = (float) $cashClosing->cop_delivered;
        $sysUsd = (float) $cashClosing->usd_delivered;
        $sysCredit = (float) $cashClosing->usd_credit;
        $sysBsMobile = (float) ($cashClosing->bs_transfer + $cashClosing->bs_mobile);
        $sysBsCard = (float) ($cashClosing->bs_card_debito + $cashClosing->bs_card_credit);

        // Si se editó COP, el sobrante y totales en COP podrían cambiar.
        // Volvemos a realizar el cálculo que se hace al cerrar.
        $sobrante = max(0, $decCop - $sysCop);
        
        $updateData = [
            'declared_cop' => $decCop,
            'declared_usd' => $decUsd,
            'declared_credit' => $decCredit,
            'declared_bs_mobile' => $decBsMobile,
            'declared_bs_card' => $decBsCard,
            'cop_spare' => $sobrante,
            // cop_delivered = valor real que debería haber + sobrante
            'cop_delivered' => $sysCop + $sobrante,
        ];

        $mismatches = [];
        $notes = [];

        if (round($decCop, 2) != round($sysCop, 2)) {
            $mismatches[] = 'cop';
            $notes[] = "COP Físico: Declarado " . number_format($decCop, 2) . " / Sistema " . number_format($sysCop, 2);
        }
        if (round($decUsd, 2) != round($sysUsd, 2)) {
            $mismatches[] = 'usd';
            $notes[] = "USD: Declarado " . number_format($decUsd, 2) . " / Sistema " . number_format($sysUsd, 2);
        }
        if (round($decCredit, 2) != round($sysCredit, 2)) {
            $mismatches[] = 'credit';
            $notes[] = "Crédito USD: Declarado " . number_format($decCredit, 2) . " / Sistema " . number_format($sysCredit, 2);
        }
        if (round($decBsMobile, 2) != round($sysBsMobile, 2)) {
            $mismatches[] = 'bs_mobile';
            $notes[] = "Transf/PM BS: Declarado " . number_format($decBsMobile, 2) . " / Sistema " . number_format($sysBsMobile, 2);
        }
        if (round($decBsCard, 2) != round($sysBsCard, 2)) {
            $mismatches[] = 'bs_card';
            $notes[] = "Tarjetas BS: Declarado " . number_format($decBsCard, 2) . " / Sistema " . number_format($sysBsCard, 2);
        }

        $updateData['blind_mismatches'] = json_encode($mismatches);
        $updateData['blind_note'] = implode(' | ', $notes);

        $cashClosing->update($updateData);
        $cashClosing->recalculateTotals();

        return response()->json([
            'status' => 'success',
            'message' => 'Cierre ciego actualizado y recalculado correctamente',
            'data' => $cashClosing->refresh()
        ]);
    }
}
