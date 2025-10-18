<?php

namespace App\Contracts;


use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

interface ExchangeRate
{

    public function consultAll(): Collection;

    public function consultOneCOP(): Model;

    public function consultOneBCV(): Model;

    public function updateBCVDollar(array $data): Model;

    public function store(array $data): Model;

}
