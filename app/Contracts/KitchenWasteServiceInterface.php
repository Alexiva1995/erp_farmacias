<?php

namespace App\Contracts;

use App\Models\KitchenWaste;
use Illuminate\Http\UploadedFile;

interface KitchenWasteServiceInterface
{
    /**
     * Lista las mermas registradas paginadas con métricas de resumen.
     */
    public function listWastes(array $filters = []): array;

    /**
     * Registra un reporte de merma en estado pendiente con foto de evidencia.
     */
    public function recordWaste(array $data, int $userId, ?UploadedFile $photo = null): KitchenWaste;

    /**
     * Aprueba una merma pendiente, descuenta del inventario y registra el movimiento como pérdida.
     */
    public function approveWaste(KitchenWaste $waste, int $approverId): KitchenWaste;

    /**
     * Rechaza una merma pendiente sin afectar el inventario.
     */
    public function rejectWaste(KitchenWaste $waste, int $approverId, ?string $reason = null): KitchenWaste;
}
