<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Fiscal\GenerateInventoryBookPeriodRequest;
use App\Http\Requests\Fiscal\IndexInventoryBookRequest;
use App\Http\Requests\Fiscal\UpdateInventoryBookEntryRequest;
use App\Http\Resources\InventoryBookEntryResource;
use App\Http\Resources\InventoryBookResource;
use App\Services\Fiscal\InventoryBookService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class InventoryBookController extends Controller
{
    public function __construct(
        protected InventoryBookService $inventoryBookService
    ) {}

    public function index(IndexInventoryBookRequest $request): JsonResponse
    {
        try {
            $this->inventoryBookService->findOrCreateCurrentBook();
        } catch (\Throwable $e) {
            Log::warning("Error al inicializar libro actual de inventario: " . $e->getMessage());
        }

        $filters = $request->validated();
        $perPage = (int) ($filters['per_page'] ?? 15);

        $books = $this->inventoryBookService->getBooks($filters, $perPage);

        return response()->json([
            'data' => InventoryBookResource::collection($books),
            'pagination' => [
                'total' => $books->total(),
                'per_page' => $books->perPage(),
                'current_page' => $books->currentPage(),
                'last_page' => $books->lastPage(),
            ],
        ]);
    }

    public function generatePeriod(GenerateInventoryBookPeriodRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $book = $this->inventoryBookService->findOrCreateBook(
            (int) $validated['year'],
            (int) $validated['month'],
            isset($validated['fortnight']) ? (int) $validated['fortnight'] : null
        );

        return response()->json([
            'message' => 'Período generado exitosamente.',
            'data' => new InventoryBookResource($book),
        ]);
    }

    public function show(int $id): JsonResponse
    {
        $book = $this->inventoryBookService->getBookById($id);

        // Si el libro está abierto, sincronizar y refrescar automáticamente al consultar
        if ($book->isOpen()) {
            $book = $this->inventoryBookService->syncTransactions($id);
        }

        return response()->json([
            'data' => new InventoryBookResource($book),
        ]);
    }

    public function sync(int $id): JsonResponse
    {
        $book = $this->inventoryBookService->syncTransactions($id);

        return response()->json([
            'message' => 'Movimientos y existencias del libro sincronizados exitosamente.',
            'data' => new InventoryBookResource($book),
        ]);
    }

    public function updateEntry(UpdateInventoryBookEntryRequest $request, int $id, int $entryId): JsonResponse
    {
        $entry = $this->inventoryBookService->updateEntry($id, $entryId, $request->validated());

        return response()->json([
            'message' => 'Renglón de inventario actualizado exitosamente.',
            'data' => new InventoryBookEntryResource($entry),
        ]);
    }

    public function toggleAdjustments(Request $request, int $id): JsonResponse
    {
        $include = (bool) $request->input('include', true);
        $book = $this->inventoryBookService->toggleAdjustmentsAsWithdrawals($id, $include);

        return response()->json([
            'message' => $include
                ? 'Ajustes negativos y pérdidas incorporados a la columna Retiros.'
                : 'Ajustes negativos excluidos de la columna Retiros.',
            'data' => new InventoryBookResource($book),
        ]);
    }

    public function close(Request $request, int $id): JsonResponse
    {
        $userId = (int) ($request->user()?->id ?? 1);
        $book = $this->inventoryBookService->closeBook($id, $userId);

        return response()->json([
            'message' => 'Libro de inventario cerrado exitosamente. El período ha sido congelado.',
            'data' => new InventoryBookResource($book),
        ]);
    }

    public function reopen(int $id): JsonResponse
    {
        $book = $this->inventoryBookService->reopenBook($id);

        return response()->json([
            'message' => 'Libro de inventario reabierto para edición.',
            'data' => new InventoryBookResource($book),
        ]);
    }

    public function exportExcel(int $id): BinaryFileResponse
    {
        return $this->inventoryBookService->exportExcel($id);
    }

    public function exportPdf(int $id)
    {
        return $this->inventoryBookService->exportPdf($id);
    }

    public function exportWithdrawalActPdf(int $id)
    {
        return $this->inventoryBookService->exportWithdrawalActPdf($id);
    }
}
