<?php

namespace App\Repository;

use App\Models\Client;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

class ClientRepository
{


    public function create(array $data): Model
    {
        $record = Client::create($data);
        return $record;
    }

    public function edit(array $data): Model
    {
        Client::where("id", "=", $data["id"])->update($data);
        return Client::find($data["id"]);
    }

    public function consultById(string $id): ?Model
    {
        $client = Client::query()->with("company")->where("id", "=", $id)->first();
        return $client;
    }

    public function consultByIdentification(string $identification): ?Model
    {
        return Client::query()->with("company")->where("identification", "=", $identification)->first();
    }

    public function consultAll(): Collection
    {
        return Client::query()->with("company")->get();
    }

    public function consultAllWithoutCompany(): Collection
    {
        return Client::query()->get();
    }

    public function deleteById(string $id): void
    {
        Client::where("id", "=", $id)->delete();
    }
}
