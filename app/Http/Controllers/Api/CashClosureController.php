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
}
