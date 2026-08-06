<?php

namespace App\Contracts\Repositories;

use App\Models\ProductPack;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface ProductPackRepositoryInterface
{
    public function getPaginated(array $filters = []): LengthAwarePaginator;
    public function validatePackConfigProducts(array $productIds): \Illuminate\Database\Eloquent\Collection;
    public function create(array $data): ProductPack;
    public function update(ProductPack $pack, array $data): ProductPack;
    public function delete(ProductPack $pack): bool;
}
