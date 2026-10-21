<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Fiscal\IndexPurchaseBookRequest;
use App\Http\Requests\Fiscal\StorePurchaseBookEntryRequest;
use App\Http\Requests\Fiscal\UpdatePurchaseBookEntryRequest;
use App\Http\Resources\PurchaseBookEntryResource;
use App\Http\Resources\PurchaseBookResource;
use App\Services\Fiscal\PurchaseBookService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class PurchaseBookController extends Controller
{
    public function __construct(
        protected PurchaseBookService $purchaseBookService
    ) {}

    public function index(IndexPurchaseBookRequest $request): JsonResponse
    {
        // Asegurar la existencia del libro del período actual
        $this->purchaseBookService->findOrCreateCurrentBook();

        $filters = $request->validated();
        $perPage = (int) ($filters['per_page'] ?? 15);

        $books = $this->purchaseBookService->getBooks($filters, $perPage);

        return response()->json([
            'data' => PurchaseBookResource::collection($books),
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

        $book = $this->purchaseBookService->findOrCreateBook(
            (int) $validated['year'],
            (int) $validated['month'],
            isset($validated['fortnight']) ? (int) $validated['fortnight'] : null
        );

        return response()->json([
            'message' => 'Período generado exitosamente.',
            'data' => new PurchaseBookResource($book),
        ]);
    }

    public function show(int $id): JsonResponse
    {
        $book = $this->purchaseBookService->getBookById($id);

        return response()->json([
            'data' => new PurchaseBookResource($book),
        ]);
    }

    public function sync(int $id): JsonResponse
    {
        $book = $this->purchaseBookService->syncTransactions($id);

        return response()->json([
            'message' => 'Transacciones de facturas y retenciones sincronizadas exitosamente.',
            'data' => new PurchaseBookResource($book),
        ]);
    }

    public function storeEntry(StorePurchaseBookEntryRequest $request, int $id): JsonResponse
    {
        $entry = $this->purchaseBookService->storeEntry($id, $request->validated());

        return response()->json([
            'message' => 'Operación agregada exitosamente al libro de compras.',
            'data' => new PurchaseBookEntryResource($entry),
        ], 201);
    }

    public function updateEntry(UpdatePurchaseBookEntryRequest $request, int $id, int $entryId): JsonResponse
    {
        $entry = $this->purchaseBookService->updateEntry($id, $entryId, $request->validated());

        return response()->json([
            'message' => 'Operación actualizada exitosamente.',
            'data' => new PurchaseBookEntryResource($entry),
        ]);
    }

    public function destroyEntry(int $id, int $entryId): JsonResponse
    {
        $this->purchaseBookService->deleteEntry($id, $entryId);

        return response()->json([
            'message' => 'Operación eliminada exitosamente del libro de compras.',
        ]);
    }

    public function close(Request $request, int $id): JsonResponse
    {
        $userId = $request->user()?->id ?? 1;
        $book = $this->purchaseBookService->closeBook($id, (int) $userId);

        return response()->json([
            'message' => 'El libro de compras ha sido cerrado definitivamente.',
            'data' => new PurchaseBookResource($book),
        ]);
    }

    public function exportExcel(int $id): BinaryFileResponse
    {
        return $this->purchaseBookService->exportExcel($id);
    }

    public function exportPdf(int $id)
    {
        return $this->purchaseBookService->exportPdf($id);
    }
}
