<?php


namespace App\Repository;

use App\Models\Company;
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


    public function filtrar($filtros, $perPage = 10): LengthAwarePaginator
    {
        $consulta = Company::query()->with("clients");


        if (array_key_exists("sortBy", $filtros) && array_key_exists("orderBy", $filtros)) {
            $consulta->orderBy($filtros["sortBy"], $filtros["orderBy"]);
        } else {
            $consulta->orderBy("name", "ASC");
        }

        return $consulta->paginate($perPage);
    }
}
