<?php

use Spatie\LaravelData\Data;

class CreateClientData extends Data
{


    public function __construct(
        public string $identification,
        public string $identification_type,
        public string $name,
        public string $last_name,
        public string $email,
        public string $phone,
        public string $address,
        public string $company_id,
    ) {}
}
