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


    public function consultAll(): Collection
    {
        return Company::query()->orderBy("name", "ASC")->get();
    }
}
