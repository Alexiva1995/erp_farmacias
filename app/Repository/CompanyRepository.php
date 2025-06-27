<?php


namespace App\Repository;

use App\Models\Company;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

class CompanyRepository
{


    public function create(array $data): Model
    {
        $record = Company::create($data);
        return $record;
    }

    public function edit(array $data): Model
    {
        $record = Company::where("id", "=", $data["id"])->update($data);
        return Company::find($data["id"]);
    }

    public function consultAll(): Collection
    {
        return Company::query()->orderBy("name", "ASC")->get();
    }

    public function consultById(string $id): ?Model
    {
        return Company::find($id);
    }

    public function deleteById(string $id): void
    {
        Company::where("id", "=", $id)->delete();
    }
}
