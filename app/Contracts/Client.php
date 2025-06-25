<?php

namespace App\Contracts;

use App\Data\CreateClientData;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

interface Client
{

    public function create(CreateClientData $data): Model;

    public function edit(array $data): Model;

    public function consultAll(): Collection;

    public function consultById(string $id): Model | null;

    public function deleteById(string $id): void;
}
