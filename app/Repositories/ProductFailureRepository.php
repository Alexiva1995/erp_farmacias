<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Models\ProductFailure;

class ProductFailureRepository
{
    /**
     * Crear un nuevo registro de falla de producto.
     */
    public function create(array $data): ProductFailure
    {
        return ProductFailure::create($data);
    }
}
