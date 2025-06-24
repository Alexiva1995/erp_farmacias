<?php

namespace App\Contracts;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

interface Client
{

    public function create(array $data): Model;

    public function edit(array $data): Model;

    public function consultAll(): Collection;

    public function consultById(string $id): Model | null;

    public function deleteById(string $id): void;
}
