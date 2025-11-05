<?php

namespace App\Contracts;

use Illuminate\Database\Eloquent\Collection;

interface ExpenseCategory
{
    public function getAll(): Collection;
}
