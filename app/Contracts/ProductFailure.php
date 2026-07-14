<?php

namespace App\Contracts;

interface ProductFailure
{
    /**
     * Almacenar un nuevo reporte de falla y enviar notificación.
     */
    public function store(array $data): \App\Models\ProductFailure;
}
