<?php


namespace App\Data;

use Spatie\LaravelData\Data;

class UpdateCompanyClientData extends Data
{

    public function __construct(
        public int $client_id,
        public int $company_id,
        public bool $status,
    ) {}
}
