<?php

declare(strict_types=1);

namespace App\Contracts;

use App\Models\PurchaseBook;
use App\Models\PurchaseBookEntry;
use Illuminate\Pagination\LengthAwarePaginator;

interface PurchaseBookContract
{
    /**
     * Obtiene el listado paginado de libros de compras con filtros.
     */
    public function getBooks(array $filters = [], int $perPage = 15): LengthAwarePaginator;

    /**
     * Obtiene o genera el libro de compras para un período específico.
     */
    public function findOrCreateBook(int $year, int $month, ?int $fortnight = null): PurchaseBook;

    /**
     * Obtiene un libro por ID con sus entradas.
     */
    public function getBookById(int $id): PurchaseBook;

    /**
     * Sincroniza las transacciones (facturas y retenciones) en un libro abierto.
     */
    public function syncTransactions(PurchaseBook $book): PurchaseBook;

    /**
     * Actualiza una fila de transacción específica del libro.
     */
    public function updateEntry(PurchaseBook $book, int $entryId, array $data): PurchaseBookEntry;

    /**
     * Agrega una entrada manual a un libro abierto.
     */
    public function storeEntry(PurchaseBook $book, array $data): PurchaseBookEntry;

    /**
     * Elimina una entrada de un libro abierto.
     */
    public function deleteEntry(PurchaseBook $book, int $entryId): bool;

    /**
     * Cierra el libro de compras bloqueando su edición.
     */
    public function closeBook(PurchaseBook $book, int $userId): PurchaseBook;

    /**
     * Recalcula los totales y el cuadro resumen de un libro.
     */
    public function recalculateTotals(PurchaseBook $book): void;
}
