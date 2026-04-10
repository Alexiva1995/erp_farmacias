<?php


namespace App\Data;

use Spatie\LaravelData\Data;

class EditDoctorData extends Data
{


    public function __construct(
        public string $id,
        public string $name,
        public string $identification,
        public string|null $address,
        public int|null $specialty_id,
    ) {}
}
