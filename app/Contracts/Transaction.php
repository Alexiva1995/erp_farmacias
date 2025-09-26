<?php

namespace App\Contracts;

interface Transaction
{
  public function getAll(array $data): array;
  public function getByType(array $data): array;
}
