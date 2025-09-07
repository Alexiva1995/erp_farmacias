<?php


namespace App\Repository;

use App\Models\Expense;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;

class ExpensesRepository
{

    public function createGasto(array $data): Expense
    {
        return Expense::create($data);
    }

    public function edit(array $data): Expense | null
    {
        Expense::where("id", "=", $data["id"])->update($data);
        return Expense::find($data["id"]);
    }

    public function consultAll(): Collection
    {
        return Expense::query()->with(["user", "category"])->orderBy("name", "ASC")->get();
    }

    public function consultById(string $id): ?Expense
    {
        return Expense::find($id);
    }

    public function deleteById(string $id): void
    {
        Expense::where("id", "=", $id)->delete();
    }

    public function buildFilter(array $filtros): Builder
    {
        $consulta = Expense::query()->with(["user", "category"]);

        if (array_key_exists("buscardor_filtro", $filtros)) {
            if ($filtros["buscardor_filtro"] != "") {
                $consulta->where(function ($query) use ($filtros) {
                    $query->where("name", "like", "%" . $filtros["buscardor_filtro"] . "%")
                        ->orWhere("id", "like", "%" . $filtros["buscardor_filtro"] . "%");
                });
            }
        }

        if (array_key_exists("count", $filtros)) {
            $consulta->where("count", "=", $filtros["count"]);
        }

        if (array_key_exists("currency", $filtros)) {
            $consulta->where("currency", "=", $filtros["currency"]);
        }

        if (array_key_exists("status", $filtros)) {
            if (count($filtros) > 0) {
                $consulta->whereIn("status", $filtros["status"]);
            }
        }

        if (array_key_exists("category_id_filtro", $filtros)) {
            $consulta->where("category_id", "=", $filtros["category_id_filtro"]);
        }

        if (array_key_exists("fechaDesde_filtro", $filtros) && array_key_exists("fechaHasta_filtro", $filtros)) {
            if ($filtros["fechaDesde_filtro"] != "" && $filtros["fechaHasta_filtro"] != "") {
                $consulta->whereBetween("created_at", [$filtros["fechaDesde_filtro"] . " 00:00:00", $filtros["fechaHasta_filtro"] . " 23:59:59"]);
            }
        }

        if (array_key_exists("sortBy", $filtros) && array_key_exists("orderBy", $filtros)) {
            $consulta->orderBy($filtros["sortBy"], $filtros["orderBy"]);
        } else {
            $consulta->orderBy("name", "ASC");
        }

        return $consulta;
    }

    public function filterWithPaginate(array $filtros, int $perPage = 10): LengthAwarePaginator
    {
        $consulta = $this->buildFilter($filtros);

        return $consulta->paginate($perPage);
    }

    public function filterWithoutPaginate(array $filtros): Collection
    {
        $consulta = $this->buildFilter($filtros);

        return $consulta->get();
    }


    public function changeStatus(int $id, string $status): Expense
    {
        Expense::where("id", "=", $id)->update([
            "status" => $status
        ]);
        return Expense::find($id);
    }
}
