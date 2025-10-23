<?php

namespace App\Contracts;


use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

interface ExchangeRate
{

    public function consultAll(): Collection;

    public function consultOneCOP(): Model | null;

    public function consultOneBCV(): Model | null;

    public function updateBCVDollar(array $data): Model;

    public function store(array $data): Model;

}
