<?php

namespace App\Contracts;


use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

interface ExchangeRate
{

    public function consultAll(): Collection;

    public function consultOneCOP(): Model;

    public function consultOneBCV(): Model;

     public function updateBCVDollar(): Model;

    //public function consultById(string $id): Model | null;

    public function store(array $data): Model;

    //public function editProduct(array $data): Model;

    //public function edit(array $data): Model;
}
