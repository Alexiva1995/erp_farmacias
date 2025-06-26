<?php

namespace App\Services;

use App\Contracts\Client;
use App\Data\CreateClientData;
use App\Data\EditClientData;
use App\Repository\ClientRepository;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

class ClientServices implements Client
{

    public function __construct(
        protected ClientRepository $clientRepository
    ) {}

    public function create(array $data): Model
    {

        return $this->clientRepository->create($data);
    }

    public function edit(array $data): Model
    {
        return $this->clientRepository->edit($data);
    }

    public function consultById(string $id): ?Model
    {
        return $this->clientRepository->consultById($id);
    }

    public function consultByIdentification(string $identification): ?Model
    {
        return $this->clientRepository->consultByIdentification($identification);
    }

    public function deleteById(string $id): void
    {
        $this->clientRepository->deleteById($id);
    }


    public function consultAll(): Collection
    {
        return $this->clientRepository->consultAll();
    }
}
