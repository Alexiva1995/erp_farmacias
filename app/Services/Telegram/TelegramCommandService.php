<?php

declare(strict_types=1);

namespace App\Services\Telegram;

use App\Contracts\Repositories\TelegramCommandRepositoryInterface;
use App\Models\TelegramCommand;
use Illuminate\Database\Eloquent\Collection;

class TelegramCommandService
{
    protected TelegramCommandRepositoryInterface $commandRepository;

    public function __construct(TelegramCommandRepositoryInterface $commandRepository)
    {
        $this->commandRepository = $commandRepository;
    }

    /**
     * Obtener el listado de comandos asociados a un módulo.
     */
    public function getCommandsForModule(string $module): Collection
    {
        return $this->commandRepository->getByModule($module);
    }

    /**
     * Alternar estado activo/inactivo de un comando.
     */
    public function toggleCommandState(int $commandId, bool $isActive): TelegramCommand
    {
        $command = $this->commandRepository->findById($commandId);
        return $this->commandRepository->toggleActive($command, $isActive);
    }

    /**
     * Actualizar los atributos de un comando de Telegram.
     */
    public function updateCommand(int $commandId, array $data): TelegramCommand
    {
        $command = $this->commandRepository->findById($commandId);
        return $this->commandRepository->update($command, $data);
    }
}
