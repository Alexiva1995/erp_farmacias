<?php


namespace App\Data;

use Spatie\LaravelData\Data;

class CreateDoctorData extends Data
{


    public function __construct(
        public string $name,
        public string $identification,
        public string|null $address,
    ) {}
}
