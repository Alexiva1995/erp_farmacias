<?php

namespace App\Services\Credits;


use App\Models\Credit;
use Illuminate\Database\Eloquent\ModelNotFoundException;


class CreditsActionService
{

 public function updateStatus(Credit $credit, string $status): Credit
    {
        if (!in_array($status, ['Active', 'Paid'])) {
            throw new \InvalidArgumentException("El estado proporcionado no es válido.");
        }

        $credit->update([
            'status' => $status,
        ]);
        return $credit;
    }

}
