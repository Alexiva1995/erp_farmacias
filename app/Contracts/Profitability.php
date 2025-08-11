<?php

namespace App\Contracts;


use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

interface Profitability
{

    public function consultOne(): Model | null;

    public function consultById(string $id): Model | null;

    public function store(array $data): Model;

    public function storeProduct(array $data): Model;

    public function editProduct(array $data): Model;

    public function edit(array $data): Model;
}
