<?php

namespace App\Contracts;

use App\Data\CreateClientData;
use App\Data\EditClientData;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

interface Client
{

    public function create(CreateClientData $data): Model;

    public function edit(EditClientData $data): Model;

    public function consultAll(): Collection;

    public function consultById(string $id): Model | null;

    public function consultByIdentification(string $identification): Model | null;

    public function deleteById(string $id): void;
}
