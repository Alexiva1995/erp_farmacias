<?php

declare(strict_types=1);

namespace App\Contracts;

use App\Models\SalesBook;
use App\Models\SalesBookEntry;
use Illuminate\Pagination\LengthAwarePaginator;

interface SalesBookContract
{
    /**
     * Obtiene el listado paginado de libros de ventas con filtros.
     */
    public function getBooks(array $filters = [], int $perPage = 15): LengthAwarePaginator;

    /**
     * Obtiene o genera el libro de ventas para un período específico.
     */
    public function findOrCreateBook(int $year, int $month, ?int $fortnight = null): SalesBook;

    /**
     * Obtiene un libro de ventas por ID con sus entradas.
     */
    public function getBookById(int $id): SalesBook;

    /**
     * Sincroniza las transacciones (Reportes Z y Notas de Crédito) en un libro abierto.
     */
    public function syncTransactions(SalesBook $book): SalesBook;

    /**
     * Actualiza una fila de transacción específica del libro.
     */
    public function updateEntry(SalesBook $book, int $entryId, array $data): SalesBookEntry;

    /**
     * Agrega una entrada manual a un libro abierto.
     */
    public function storeEntry(SalesBook $book, array $data): SalesBookEntry;

    /**
     * Elimina una entrada de un libro abierto.
     */
    public function deleteEntry(SalesBook $book, int $entryId): bool;

    /**
     * Cierra el libro de ventas bloqueando su edición.
     */
    public function closeBook(SalesBook $book, int $userId): SalesBook;

    /**
     * Recalcula los totales acumulados de un libro de ventas.
     */
    public function recalculateTotals(SalesBook $book): void;
}
