<?php


namespace App\Repository;

use App\Models\AutoOrder;
use DateTime;

class AutoOrdersRepository
{


    public function create(array $datos): ?AutoOrder
    {
        $record = AutoOrder::create($datos);
        return $record;
    }
}
