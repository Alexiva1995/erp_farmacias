<?php

declare(strict_types=1);

namespace App\Repositories\Eloquent;

use App\Contracts\Repositories\SupplierRepositoryInterface;
use App\Models\Supplier;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

class SupplierRepository implements SupplierRepositoryInterface
{
    /**
     * @inheritDoc
     */
    public function getQuery(): Builder
    {
        return Supplier::query()
            ->withoutTrashed()
            ->with(['latestScore', 'paymentRules', 'paymentDate']);
    }

    /**
     * @inheritDoc
     */
    public function find(int $id): ?Supplier
    {
        return Supplier::find($id);
    }

    /**
     * @inheritDoc
     */
    public function create(array $data): Supplier
    {
        return Supplier::create($data);
    }

    /**
     * @inheritDoc
     */
    public function update(Supplier $supplier, array $data): Supplier
    {
        $supplier->update($data);
        return $supplier;
    }

    /**
     * @inheritDoc
     */
    public function delete(Supplier $supplier): ?bool
    {
        return $supplier->delete();
    }
}
