<?php

namespace App\Contracts;

use Illuminate\Http\UploadedFile;

interface ReceiptOcrServiceInterface
{
    /**
     * Extrae el número de referencia bancario desde un comprobante (imagen o PDF).
     */
    public function extractReference(UploadedFile|string $file): ?string;
}
