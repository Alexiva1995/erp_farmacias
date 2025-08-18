<?php


namespace App\Repository;

use App\Models\AutoOrderDetail;

class AutoOrderDetailsRepository
{


    public function create(array $datos): ?AutoOrderDetail
    {
        $record = AutoOrderDetail::create($datos);
        return $record;
    }
}
