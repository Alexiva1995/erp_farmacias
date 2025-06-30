<?php


namespace App\Data;

use Spatie\LaravelData\Data;

class CreateCompanyData extends Data
{


    public function __construct(
        public string $name,
        public string $identification,
        public string|null $address,
        public string $type_company,
    ) {}
}
