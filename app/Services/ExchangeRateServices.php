<?php

namespace App\Services;

use App\Contracts\ExchangeRate;
use App\Repository\ExchangeRateRepository;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

class ExchangeRateServices implements ExchangeRate
{

    public function __construct(protected ExchangeRateRepository $exchangeRateRepository) {}

    public function consultAll(): Collection
    {
        return $this->exchangeRateRepository->consultAll();
    }

    public function store(array $data): Model
    {
        return $this->exchangeRateRepository->store($data);
    }

    public function consultOneCOP(): Model
    {
        return $this->exchangeRateRepository->consultOneCOP();
    }

    /*
    public function consultById(string $id): ?Model
    {
        return $this->ExchangeRateRepository->consultById($id);
    }

    

    public function editProduct(array $data): Model
    {
        return $this->ExchangeRateRepository->editProduct($data);
    }

    public function edit(array $data): Model
    {
        return $this->ExchangeRateRepository->edit($data);
    }*/
}
