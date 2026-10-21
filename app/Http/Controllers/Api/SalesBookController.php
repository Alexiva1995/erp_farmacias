<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Fiscal\IndexSalesBookRequest;
use App\Http\Requests\Fiscal\StoreSalesBookEntryRequest;
use App\Http\Requests\Fiscal\UpdateSalesBookEntryRequest;
use App\Http\Resources\SalesBookEntryResource;
use App\Http\Resources\SalesBookResource;
use App\Services\Fiscal\SalesBookService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class SalesBookController extends Controller
{
    public function __construct(
        protected SalesBookService $salesBookService
    ) {}

    public function index(IndexSalesBookRequest $request): JsonResponse
    {
        try {
            $this->salesBookService->findOrCreateCurrentBook();
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning("Error al inicializar libro actual de ventas: " . $e->getMessage());
        }

        $filters = $request->validated();
        $perPage = (int) ($filters['per_page'] ?? 15);

        $books = $this->salesBookService->getBooks($filters, $perPage);

        return response()->json([
            'data' => SalesBookResource::collection($books),
            'pagination' => [
                'total' => $books->total(),
                'per_page' => $books->perPage(),
                'current_page' => $books->currentPage(),
                'last_page' => $books->lastPage(),
            ],
        ]);
    }

    public function generatePeriod(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'year' => ['required', 'integer', 'min:2020', 'max:2100'],
            'month' => ['required', 'integer', 'min:1', 'max:12'],
            'fortnight' => ['nullable', 'integer', 'in:1,2'],
        ]);

        $book = $this->salesBookService->findOrCreateBook(
            (int) $validated['year'],
            (int) $validated['month'],
            isset($validated['fortnight']) ? (int) $validated['fortnight'] : null
        );

        return response()->json([
            'message' => 'Período de ventas generado exitosamente.',
            'data' => new SalesBookResource($book),
        ]);
    }

    public function show(int $id): JsonResponse
    {
        $book = $this->salesBookService->getBookById($id);

        return response()->json([
            'data' => new SalesBookResource($book),
        ]);
    }

    public function sync(int $id): JsonResponse
    {
        $book = $this->salesBookService->syncTransactions($id);

        return response()->json([
            'message' => 'Transacciones de Reportes Z y Notas de Crédito sincronizadas exitosamente.',
            'data' => new SalesBookResource($book),
        ]);
    }

    public function storeEntry(StoreSalesBookEntryRequest $request, int $id): JsonResponse
    {
        $entry = $this->salesBookService->storeEntry($id, $request->validated());

        return response()->json([
            'message' => 'Operación agregada exitosamente al libro de ventas.',
            'data' => new SalesBookEntryResource($entry),
        ], 201);
    }

    public function updateEntry(UpdateSalesBookEntryRequest $request, int $id, int $entryId): JsonResponse
    {
        $entry = $this->salesBookService->updateEntry($id, $entryId, $request->validated());

        return response()->json([
            'message' => 'Operación actualizada exitosamente.',
            'data' => new SalesBookEntryResource($entry),
        ]);
    }

    public function destroyEntry(int $id, int $entryId): JsonResponse
    {
        $this->salesBookService->deleteEntry($id, $entryId);

        return response()->json([
            'message' => 'Operación eliminada exitosamente del libro de ventas.',
        ]);
    }

    public function close(Request $request, int $id): JsonResponse
    {
        $userId = $request->user()?->id ?? 1;
        $book = $this->salesBookService->closeBook($id, (int) $userId);

        return response()->json([
            'message' => 'El libro de ventas ha sido cerrado definitivamente.',
            'data' => new SalesBookResource($book),
        ]);
    }

    public function exportExcel(int $id): BinaryFileResponse
    {
        return $this->salesBookService->exportExcel($id);
    }

    public function exportPdf(int $id)
    {
        return $this->salesBookService->exportPdf($id);
    }
}
