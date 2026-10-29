<?php

declare(strict_types=1);

namespace App\Contracts;

use App\Models\InventoryBook;
use App\Models\InventoryBookEntry;
use Illuminate\Pagination\LengthAwarePaginator;

interface InventoryBookContract
{
    /**
     * Obtiene el listado paginado de libros de inventario con filtros.
     */
    public function getBooks(array $filters = [], int $perPage = 15): LengthAwarePaginator;

    /**
     * Obtiene o genera el libro de inventario para un período específico.
     */
    public function findOrCreateBook(int $year, int $month, ?int $fortnight = null): InventoryBook;

    /**
     * Obtiene un libro por ID con sus entradas.
     */
    public function getBookById(int $id): InventoryBook;

    /**
     * Sincroniza y recalcula las existencias y movimientos de un libro abierto.
     */
    public function syncTransactions(InventoryBook $book): InventoryBook;

    /**
     * Actualiza una fila de producto específica del libro de inventario.
     */
    public function updateEntry(InventoryBook $book, int $entryId, array $data): InventoryBookEntry;

    /**
     * Alterna la inclusión de ajustes negativos como retiros.
     */
    public function toggleAdjustmentsAsWithdrawals(InventoryBook $book, bool $include): InventoryBook;

    /**
     * Cierra el libro de inventario bloqueando su edición.
     */
    public function closeBook(InventoryBook $book, int $userId): InventoryBook;

    /**
     * Reabre un libro previamente cerrado.
     */
    public function reopenBook(InventoryBook $book): InventoryBook;

    /**
     * Recalcula los totales consolidados del libro.
     */
    public function recalculateTotals(InventoryBook $book): void;
}
