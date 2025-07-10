<?php


namespace App\Repository;

use App\Models\Company;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Pagination\LengthAwarePaginator;

class CompanyRepository
{


    public function create(array $data): Model
    {
        $record = Company::create($data);
        return $record;
    }

    public function edit(array $data): Model
    {
        Company::where("id", "=", $data["id"])->update($data);
        return Company::find($data["id"]);
    }

    public function consultAll(): Collection
    {
        return Company::query()->with("clients")->orderBy("name", "ASC")->get();
    }

    public function consultById(string $id): ?Model
    {
        return Company::find($id);
    }

    public function deleteById(string $id): void
    {
        Company::where("id", "=", $id)->delete();
    }

    public function builerPaginate($filtros): Builder
    {
        $consulta = Company::query()->with("clients");

        if (array_key_exists("buscardor_filtro", $filtros)) {
            if ($filtros["buscardor_filtro"] != "") {
                $consulta->where(function ($query) use ($filtros) {
                    $query->where("name", "like", "%" . $filtros["buscardor_filtro"] . "%")
                        ->orWhere("address", "like", "%" . $filtros["buscardor_filtro"] . "%")
                        ->orWhere("identification", "like", "%" . $filtros["buscardor_filtro"] . "%");
                });
            }
        }

        if (array_key_exists("tipo_empresa_filtro", $filtros)) {
            if ($filtros["tipo_empresa_filtro"] != "") {
                $consulta->where("type_company", $filtros["tipo_empresa_filtro"]);
            }
        }


        if (array_key_exists("fechaDesde_filtro", $filtros) && array_key_exists("fechaHasta_filtro", $filtros)) {
            if ($filtros["fechaDesde_filtro"] != "" && $filtros["fechaDesde_filtro"] != "") {
                $consulta->whereBetween("created_at", [$filtros["fechaDesde_filtro"], $filtros["fechaHasta_filtro"]]);
            }
        }


        if (array_key_exists("sortBy", $filtros) && array_key_exists("orderBy", $filtros)) {
            $consulta->orderBy($filtros["sortBy"], $filtros["orderBy"]);
        } else {
            $consulta->orderBy("name", "ASC");
        }

        return $consulta;
    }


    public function filtrar($filtros, $perPage = 10): LengthAwarePaginator
    {
        $consulta = $this->builerPaginate($filtros);

        return $consulta->paginate($perPage);
    }

    public function filterWithoutPaginate($filtros): Collection
    {

        $consulta = $this->builerPaginate($filtros);

        return $consulta->get();
    }
}
