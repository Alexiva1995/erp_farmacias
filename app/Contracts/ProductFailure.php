<?php

namespace App\Contracts;

use App\Models\ProductFailure;

interface ProductFailure
{
    /**
     * Almacenar un nuevo reporte de falla y enviar notificación.
     */
    public function store(array $data): ProductFailure;
}
