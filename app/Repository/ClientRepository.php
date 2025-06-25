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
        $record = $this->consultById($data["id"]);
        $record->update($data);
        return $record;
    }

    public function consultById(string $id): Model
    {
        return Client::find($id);
    }

    public function consultAll(): Collection
    {
        return Client::all();
    }

    public function deleteById(string $id): void
    {
        Client::where("id", "=", $id)->delete();
    }
}
