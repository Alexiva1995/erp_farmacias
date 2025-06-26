<?php


namespace App\Services;

use App\Contracts\Company;
use App\Repository\CompanyRepository;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

class CompanyServices implements Company
{

    public function __construct(
        protected CompanyRepository $companyRepository
    ) {}

    public function consultAll(): Collection
    {
        return $this->companyRepository->consultAll();
    }
}
