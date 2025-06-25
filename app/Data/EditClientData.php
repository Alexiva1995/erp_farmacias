<?php

namespace App\Data;

use DateTime;
use Spatie\LaravelData\Attributes\WithCast;
use Spatie\LaravelData\Casts\DateTimeInterfaceCast;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Optional;

class EditClientData extends Data
{


    public function __construct(
        public int $id,
        public string $identification,
        public string $identification_type,
        public string $name,
        public string $last_name,
        public string $email,
        public string $phone,
        public string $address,
        public string $company_id,
        #[WithCast(DateTimeInterfaceCast::class, format: 'Y/m/d')]
        public DateTime|Optional $birthdate,
    ) {}
}
