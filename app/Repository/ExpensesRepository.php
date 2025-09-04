<?php


namespace App\Repository;

use App\Models\Expense;
use Illuminate\Database\Eloquent\Collection;

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
        return Expense::query()->orderBy("name", "ASC")->get();
    }

    public function consultById(string $id): ?Expense
    {
        return Expense::find($id);
    }

    public function deleteById(string $id): void
    {
        Expense::where("id", "=", $id)->delete();
    }


    // public function()





}
