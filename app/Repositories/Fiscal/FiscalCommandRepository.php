<?php

namespace App\Repositories\Fiscal;

use App\Contracts\Fiscal\FiscalCommandRepositoryInterface;
use App\Models\FiscalCommand;
use Illuminate\Support\Collection;

class FiscalCommandRepository implements FiscalCommandRepositoryInterface
{
    public function create(array $data): FiscalCommand
    {
        return FiscalCommand::create([
            'command' => $data['command'],
            'payload' => $data['payload'] ?? null,
            'status' => 'pending',
        ]);
    }

    public function getNextPending(): ?FiscalCommand
    {
        return FiscalCommand::where('status', 'pending')
            ->orderBy('created_at', 'asc')
            ->first();
    }

    public function update(int $id, array $data): bool
    {
        $command = FiscalCommand::find($id);
        if (!$command) return false;

        return $command->update($data);
    }

    public function getHistory(int $limit = 20): Collection
    {
        return FiscalCommand::orderBy('created_at', 'desc')
            ->limit($limit)
            ->get();
    }
}
