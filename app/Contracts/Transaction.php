<?php

namespace App\Contracts;

use App\Models\Expense;
use App\Models\Transaction as ModelsTransaction;

interface Transaction
{
  public function getAll(array $data): array;
  public function getByType(array $data): array;
  public function createTransactionSalida(Expense $expense): ?ModelsTransaction;
}
