<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Quotation;
use App\Services\Quotation\QuotationActionService;
use App\Services\Quotation\QuotationQueryService;
use App\Models\Product;
use App\Http\Requests\StoreQuotationRequest;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class QuotationController extends Controller
{
    public function __construct(
        private QuotationQueryService $quotationQueryService,
        private QuotationActionService $quotationActionService
    ) {
    }

    public function index(Request $request)
    {
        $perPage = (int) $request->input('itemsPerPage', 10);
        $page    = (int) $request->input('page', 1);

        // Construir el query filtrado una sola vez; se reutiliza para count y datos.
        $filteredQuery = $this->quotationQueryService->getFilteredQuery($request);

        if ($perPage < 1) {
            $items = $filteredQuery->get();
            return response()->json(['data' => $items, 'total' => $items->count()]);
        }

        // Clonar para el count sin ORDER BY ni LIMIT (más eficiente)
        $total  = $this->quotationQueryService->getCountQueryProduct($request)->count();
        $offset = ($page - 1) * $perPage;
        $items  = $filteredQuery->skip($offset)->take($perPage)->get();

        return response()->json([
            'data'  => $items,
            'total' => $total,
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreQuotationRequest $request)
    {
        try {
            $quotation = $this->quotationActionService->createQuotation($request->validated());
            return response()->json([
                'message' => 'Cotización guardada exitosamente.',
                'quotation' => $quotation
            ], 201);
        } catch (\Exception $e) {
            Log::error('Error al procesar solicitud de creación de cotización en el controlador: ' . $e->getMessage(), [
                'request_data' => $request->validated(),
                'trace' => $e->getTraceAsString(),
            ]);
            return response()->json([
                'message' => 'Ocurrió un error al guardar la cotización. Por favor, inténtalo de nuevo.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show($productId)
    {
        try {
            $product = Product::find($productId);
            
            if (!$product) {
                return response()->json([
                    'status' => 'error',
                    'message' => "Producto no encontrado.",
                ], 404);
            }

            $detailedProduct = $this->quotationActionService->loadProductDetails($product);

            if (!$detailedProduct) {
                return response()->json([
                    'status' => 'error',
                    'message' => "Error al cargar los detalles del producto.",
                ], 500);
            }

            // Convertir a array y asegurar que valid_stock_sum esté presente
            $productArray = $detailedProduct->toArray();
            
            // Asegurar que valid_stock_sum esté en el array si no está
            if (!isset($productArray['valid_stock_sum'])) {
                $productArray['valid_stock_sum'] = $detailedProduct->getAttribute('valid_stock_sum') ?? 0;
            }

            return response()->json($productArray);
        } catch (\Exception $e) {
            Log::error('Error loading product details: ' . $e->getMessage(), [
                'product_id' => $productId,
                'trace' => $e->getTraceAsString(),
            ]);
            return response()->json([
                'status' => 'error',
                'message' => "Error al cargar los detalles del producto: " . $e->getMessage(),
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function showProducts(int|string $quotationId)
    {
        $quotationId = (int) $quotationId;
        $quotation = $this->quotationActionService->getProducts($quotationId);

        if (!$quotation) {
            return response()->json(['message' => 'Quotation not found'], 404);
        }

        $quotation->load('client');

        return response()->json([
            'quotation_id' => $quotation->id,
            'products' => $quotation->products,
            'client' => $quotation->client,
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        //
    }

    public function getLastNumber()
    {
        $quotation = $this->quotationActionService->getLastNumber();

        if (!$quotation) {
            return response()->json(['message' => 'Quotation not found'], 404);
        }
        return response()->json([
            'quotation_id' => $quotation->id,
        ]);
    }

    /**
     * Listado de cotizaciones realizadas (para TPV orderGeneral).
     */
    public function list(Request $request)
    {
        $query = Quotation::query()
            ->with(['client', 'creator']);

        $sortBy = $request->input('sortBy');
        $orderBy = strtolower($request->input('orderBy', 'desc')) === 'asc' ? 'asc' : 'desc';

        if (!empty($sortBy)) {
            switch ($sortBy) {
                case 'id':
                    $query->orderBy('id', $orderBy);
                    break;
                case 'total':
                    $query->orderBy('total', $orderBy);
                    break;
                case 'currency':
                    $query->orderBy('currency', $orderBy);
                    break;
                case 'created_at':
                    $query->orderBy('created_at', $orderBy);
                    break;
                default:
                    $query->orderBy('id', 'desc');
                    break;
            }
        } else {
            $query->orderBy('id', 'desc');
        }

        if ($request->filled('start_date')) {
            $start = Carbon::parse($request->start_date)->startOfDay();
            $query->where('created_at', '>=', $start);
        }
        if ($request->filled('end_date')) {
            $end = Carbon::parse($request->end_date)->endOfDay();
            $query->where('created_at', '<=', $end);
        }
        if ($request->filled('q')) {
            $term = '%' . $request->q . '%';
            $query->where(function ($q) use ($term) {
                $q->where('id', 'like', $term)
                    ->orWhereHas('client', fn ($c) => $c->where('name', 'like', $term)->orWhere('identification', 'like', $term))
                    ->orWhereHas('creator', fn ($u) => $u->where('username', 'like', $term));
            });
        }

        $perPage = (int) $request->input('itemsPerPage', 10);
        $page = (int) $request->input('page', 1);

        if ($perPage < 1) {
            $items = $query->get();
            return response()->json(['data' => $items, 'total' => $items->count()]);
        }
        $paginated = $query->paginate($perPage, ['*'], 'page', $page);
        return response()->json(['data' => $paginated->items(), 'total' => $paginated->total()]);
    }
}
