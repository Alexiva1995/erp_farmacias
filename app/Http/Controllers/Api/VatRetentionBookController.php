<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Fiscal\GenerateVatRetentionBookPeriodRequest;
use App\Http\Requests\Fiscal\IndexVatRetentionBookRequest;
use App\Http\Requests\Fiscal\UpdateVatRetentionBookEntryRequest;
use App\Http\Resources\VatRetentionBookEntryResource;
use App\Http\Resources\VatRetentionBookResource;
use App\Services\Fiscal\VatRetentionBookService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class VatRetentionBookController extends Controller
{
    public function __construct(
        protected VatRetentionBookService $vatRetentionBookService
    ) {}

    public function index(IndexVatRetentionBookRequest $request): JsonResponse
    {
        try {
            $this->vatRetentionBookService->findOrCreateCurrentBook();
        } catch (\Throwable $e) {
            Log::warning("Error al inicializar período actual de retenciones de IVA: " . $e->getMessage());
        }

        $filters = $request->validated();
        $perPage = (int) ($filters['per_page'] ?? 15);

        $books = $this->vatRetentionBookService->getBooks($filters, $perPage);

        return response()->json([
            'data' => VatRetentionBookResource::collection($books),
            'pagination' => [
                'total' => $books->total(),
                'per_page' => $books->perPage(),
                'current_page' => $books->currentPage(),
                'last_page' => $books->lastPage(),
            ],
        ]);
    }

    public function generatePeriod(GenerateVatRetentionBookPeriodRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $book = $this->vatRetentionBookService->findOrCreateBook(
            (int) $validated['year'],
            (int) $validated['month'],
            isset($validated['fortnight']) ? (int) $validated['fortnight'] : null
        );

        return response()->json([
            'message' => 'Período generado exitosamente.',
            'data' => new VatRetentionBookResource($book),
        ]);
    }

    public function show(int $id): JsonResponse
    {
        $book = $this->vatRetentionBookService->getBookById($id);

        if ($book->isOpen()) {
            $book = $this->vatRetentionBookService->syncTransactions($id);
        }

        return response()->json([
            'data' => new VatRetentionBookResource($book),
        ]);
    }

    public function sync(int $id): JsonResponse
    {
        $book = $this->vatRetentionBookService->syncTransactions($id);

        return response()->json([
            'message' => 'Comprobantes y retenciones del período sincronizados exitosamente.',
            'data' => new VatRetentionBookResource($book),
        ]);
    }

    public function updateEntry(UpdateVatRetentionBookEntryRequest $request, int $id, int $entryId): JsonResponse
    {
        $entry = $this->vatRetentionBookService->updateEntry($id, $entryId, $request->validated());

        return response()->json([
            'message' => 'Renglón de retención actualizado exitosamente.',
            'data' => new VatRetentionBookEntryResource($entry),
        ]);
    }

    public function close(Request $request, int $id): JsonResponse
    {
        $userId = (int) ($request->user()?->id ?? 1);
        $book = $this->vatRetentionBookService->closeBook($id, $userId);

        return response()->json([
            'message' => 'Reporte de retenciones cerrado y congelado exitosamente.',
            'data' => new VatRetentionBookResource($book),
        ]);
    }

    public function reopen(int $id): JsonResponse
    {
        $book = $this->vatRetentionBookService->reopenBook($id);

        return response()->json([
            'message' => 'Reporte de retenciones reabierto para ajustes.',
            'data' => new VatRetentionBookResource($book),
        ]);
    }

    public function exportTxt(int $id): Response
    {
        return $this->vatRetentionBookService->exportTxt($id);
    }

    public function exportExcel(int $id): BinaryFileResponse
    {
        return $this->vatRetentionBookService->exportExcel($id);
    }

    public function exportPdf(int $id)
    {
        return $this->vatRetentionBookService->exportPdf($id);
    }
}
