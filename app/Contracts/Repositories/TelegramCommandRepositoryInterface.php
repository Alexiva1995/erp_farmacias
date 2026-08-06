<?php

declare(strict_types=1);

namespace App\Contracts\Repositories;

use App\Models\TelegramCommand;
use Illuminate\Database\Eloquent\Collection;

interface TelegramCommandRepositoryInterface
{
    /**
     * Obtener todos los comandos pertenecientes a un módulo específico con relaciones optimizadas.
     */
    public function getByModule(string $module): Collection;

    /**
     * Buscar un comando por su ID.
     */
    public function findById(int $id): TelegramCommand;

    /**
     * Actualizar los datos de un comando.
     */
    public function update(TelegramCommand $command, array $data): TelegramCommand;

    /**
     * Alternar el estado de activación de un comando.
     */
    public function toggleActive(TelegramCommand $command, bool $isActive): TelegramCommand;
}
