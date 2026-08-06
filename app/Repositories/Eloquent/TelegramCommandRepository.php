<?php

declare(strict_types=1);

namespace App\Repositories\Eloquent;

use App\Contracts\Repositories\TelegramCommandRepositoryInterface;
use App\Models\TelegramCommand;
use Illuminate\Database\Eloquent\Collection;

class TelegramCommandRepository implements TelegramCommandRepositoryInterface
{
    /**
     * {@inheritdoc}
     */
    public function getByModule(string $module): Collection
    {
        return TelegramCommand::query()
            ->with(['channel:id,name,chat_id,is_active'])
            ->select([
                'id',
                'module',
                'channel_id',
                'command',
                'alias',
                'description',
                'is_active',
                'payload_template',
                'created_at',
                'updated_at',
            ])
            ->where('module', $module)
            ->orderBy('id', 'asc')
            ->get();
    }

    /**
     * {@inheritdoc}
     */
    public function findById(int $id): TelegramCommand
    {
        return TelegramCommand::query()
            ->with(['channel:id,name,chat_id,is_active'])
            ->findOrFail($id);
    }

    /**
     * {@inheritdoc}
     */
    public function update(TelegramCommand $command, array $data): TelegramCommand
    {
        $command->update($data);
        return $command->fresh(['channel:id,name,chat_id,is_active']);
    }

    /**
     * {@inheritdoc}
     */
    public function toggleActive(TelegramCommand $command, bool $isActive): TelegramCommand
    {
        $command->update(['is_active' => $isActive]);
        return $command->fresh(['channel:id,name,chat_id,is_active']);
    }
}
