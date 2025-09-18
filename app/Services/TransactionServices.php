<?php

namespace App\Services;

use App\Contracts\Transaction;
use App\Repository\TransactionRepository;

class TransactionServices implements Transaction
{
  public function __construct(
    protected TransactionRepository $transactionRepository
  ) {
  }

  public function getAll(array $data): array
  {
    return $this->transactionRepository->getAll($data);
  }

  public function getByType(array $data): array
  {
    return $this->transactionRepository->getByType($data);
  }
}
