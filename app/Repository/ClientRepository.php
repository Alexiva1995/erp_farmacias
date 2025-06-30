<?php

namespace App\Repository;

use App\Models\Client;
use Illuminate\Contracts\Pagination\Paginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Log;

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


    public function filtrar($filtros, $perPage = 10): LengthAwarePaginator
    {
        $consulta = Client::query()->with("company");


        if (array_key_exists("tipo", $filtros)) {
            $consulta->whereIn("identification_type", $filtros["tipo"]);
        }

        if (array_key_exists("company_id", $filtros)) {
            $consulta->where("company_id", "=", $filtros["company_id"]);
        }

        if (array_key_exists("sortBy", $filtros) && array_key_exists("orderBy", $filtros)) {
            $consulta->orderBy($filtros["sortBy"], $filtros["orderBy"]);
        } else {
            $consulta->orderBy("name", "ASC");
        }

        return $consulta->paginate($perPage);
    }
}
