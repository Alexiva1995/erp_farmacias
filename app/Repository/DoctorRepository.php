<?php

namespace App\Repository;

use App\Models\Doctor;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Pagination\LengthAwarePaginator;

class DoctorRepository
{

    public function create(array $data): Model
    {
        $record = Doctor::create($data);
        return $record;
    }

    public function edit(array $data): Model
    {
        Doctor::where("id", "=", $data["id"])->update($data);
        return Doctor::with('specialty')->find($data["id"]);
    }

    public function consultAll(): Collection
    {
        return Doctor::with('specialty')->orderBy("name", "ASC")->get();
    }

    public function consultById(string $id): ?Model
    {
        return Doctor::with('specialty')->find($id);
    }

    public function deleteById(string $id): void
    {
        Doctor::where("id", "=", $id)->delete();
    }

    public function builerPaginate($filtros): Builder
    {
        $consulta = Doctor::with('specialty');

        if (array_key_exists("buscardor_filtro", $filtros)) {
            if ($filtros["buscardor_filtro"] != "") {
                $consulta->where(function ($query) use ($filtros) {
                    $query->where("name", "like", "%" . $filtros["buscardor_filtro"] . "%")
                        ->orWhere("address", "like", "%" . $filtros["buscardor_filtro"] . "%")
                        ->orWhere("identification", "like", "%" . $filtros["buscardor_filtro"] . "%")
                        ->orWhereHas('specialty', function ($q) use ($filtros) {
                            $q->where('name', 'like', "%" . $filtros["buscardor_filtro"] . "%");
                        });
                });
            }
        }

        if (array_key_exists("fechaDesde_filtro", $filtros) && array_key_exists("fechaHasta_filtro", $filtros)) {
            if ($filtros["fechaDesde_filtro"] != "" && $filtros["fechaHasta_filtro"] != "") {
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

    public function consultByIdentification(string $identification): ?Model
    {
        return Doctor::query()->where("identification", "=", $identification)->first();
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
