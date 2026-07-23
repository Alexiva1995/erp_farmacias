<?php

declare(strict_types=1);

namespace App\Services;

use App\Contracts\Transaction;
use App\Data\CreateTransactionData;
use App\Models\Expense;
use App\Models\Transaction as ModelsTransaction;
use App\Repositories\TransactionRepository;
use DateTime;
use DateTimeZone;

class TransactionServices implements Transaction
{
  public function __construct(
    protected TransactionRepository $transactionRepository,
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

  public function getWallets(array $data): array
  {
    return $this->transactionRepository->getWallets($data);
  }

  public function getIncomeSummary(array $data): array
  {
    return $this->transactionRepository->getIncomeSummaryByMethod($data);
  }

  public function adjustBalance(array $data): void
  {
    $this->transactionRepository->adjustBalance($data);
  }

  public function createTransactionSalida(Expense $expense): ?ModelsTransaction
  {
    $timeZone = new DateTimeZone(config("app.timezone"));
    $hoy = new DateTime("now", $timeZone);

    $data = CreateTransactionData::from([
      "user_id" => $expense->user_id,
      "category_id" => $expense->category_id,
      "description" => $expense->name,
      "currency" => $expense->currency,
      "type" => $expense->count,
      "amount" => $expense->amount,
      "movement_type" => "OUT",
      "transaction_date" => $hoy->format("Y-m-d")
    ]);

    return $this->transactionRepository->create($data);
  }
}
