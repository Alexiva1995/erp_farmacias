<?php

namespace App\Services;

use App\Contracts\Profitability;
use App\Repository\ProfitabilityRepository;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

class ProfitabilityServices implements Profitability
{

    public function __construct(protected ProfitabilityRepository $profitabilityRepository) {}

    public function consultOne(): Model | null
    {
        return $this->profitabilityRepository->consultOne();
    }

    public function consultById(string $id): ?Model
    {
        return $this->profitabilityRepository->consultById($id);
    }

    public function store(array $data): Model
    {
        return $this->profitabilityRepository->store($data);
    }

    public function storeProduct(array $data): Model
    {
        return $this->profitabilityRepository->storeProduct($data);
    }

    public function editProduct(array $data): Model
    {
        return $this->profitabilityRepository->editProduct($data);
    }

    public function edit(array $data): Model
    {
        return $this->profitabilityRepository->edit($data);
    }
}
