<?php

namespace App\Contracts\Repositories;

use App\Models\Supplier;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;

interface SupplierRepositoryInterface
{
    /**
     * Obtiene una consulta base filtrable para proveedores.
     *
     * @return Builder
     */
    public function getQuery(): Builder;

    /**
     * Busca un proveedor por su ID.
     *
     * @param int $id
     * @return Supplier|null
     */
    public function find(int $id): ?Supplier;

    /**
     * Crea un nuevo proveedor.
     *
     * @param array $data
     * @return Supplier
     */
    public function create(array $data): Supplier;

    /**
     * Actualiza un proveedor existente.
     *
     * @param Supplier $supplier
     * @param array $data
     * @return Supplier
     */
    public function update(Supplier $supplier, array $data): Supplier;

    /**
     * Elimina un proveedor.
     *
     * @param Supplier $supplier
     * @return bool|null
     */
    public function delete(Supplier $supplier): ?bool;
}
