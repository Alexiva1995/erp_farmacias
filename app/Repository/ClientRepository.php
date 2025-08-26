<?php

namespace App\Repository;

use App\Models\Client;
use Illuminate\Contracts\Pagination\Paginator;
use Illuminate\Database\Eloquent\Builder;
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

    public function builerPaginate($filtros): Builder
    {
        $consulta = Client::query()->with(["company" => function ($query) {
            $query->withTrashed();
        }]);

        if (array_key_exists("buscardor_filtro", $filtros)) {
            if ($filtros["buscardor_filtro"] != "") {
                $consulta->where(function ($query) use ($filtros) {
                    $query->whereRaw("CONCAT(name,' ',last_name) LIKE ?", ["%{$filtros["buscardor_filtro"]}%"])
                        ->orWhereRaw("CONCAT(last_name,' ',name) LIKE ?", ["%{$filtros["buscardor_filtro"]}%"])
                        ->orWhere("name", "like", "%" . $filtros["buscardor_filtro"] . "%")
                        ->orWhere("last_name", "like", "%" . $filtros["buscardor_filtro"] . "%")
                        ->orWhere("address", "like", "%" . $filtros["buscardor_filtro"] . "%")
                        ->orWhere("identification", "like", "%" . $filtros["buscardor_filtro"] . "%");
                });
            }
        }

        if (array_key_exists("fechaDesde_filtro", $filtros) && array_key_exists("fechaHasta_filtro", $filtros)) {
            if ($filtros["fechaDesde_filtro"] != "" && $filtros["fechaHasta_filtro"] != "") {
                $consulta->whereBetween("created_at", [$filtros["fechaDesde_filtro"], $filtros["fechaHasta_filtro"]]);
            }
        }

        if (!array_key_exists("tipo_identificacion_filtro", $filtros)) {
            if (array_key_exists("tipo", $filtros)) {
                $consulta->whereIn("identification_type", $filtros["tipo"]);
            }
        }


        if (array_key_exists("tipo_identificacion_filtro", $filtros)) {
            if ($filtros["tipo_identificacion_filtro"] != "") {
                $consulta->where("identification_type", $filtros["tipo_identificacion_filtro"]);
            } else {
                $consulta->whereIn("identification_type", $filtros["tipo"]);
            }
        }

        if (array_key_exists("company_id", $filtros)) {
            $consulta->where("company_id", "=", $filtros["company_id"]);
        }

        if (array_key_exists("sortBy", $filtros) && array_key_exists("orderBy", $filtros)) {
            $consulta->orderBy($filtros["sortBy"], $filtros["orderBy"]);
        } else {
            $consulta->orderBy("name", "ASC");
        }

        // $consulta->orderBy("name", "ASC");

        return $consulta;
    }

    public function filterWithoutPaginate($filtros): Collection
    {

        $consulta = $this->builerPaginate($filtros);

        return $consulta->get();
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
        $consulta = $this->builerPaginate($filtros);

        return $consulta->paginate($perPage);
    }
}
