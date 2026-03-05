<?php

namespace App\Contracts;

use App\Models\Retention as MRetention;
use Illuminate\Pagination\LengthAwarePaginator;

interface Retention
{
    public function getInvoicesWithTax(array $filters, int $perPage): LengthAwarePaginator;
    public function generateRetentions(array $invoiceIds): MRetention;
    public function getGeneratedRetentions(array $filters, int $perPage): LengthAwarePaginator;
    public function prepareRetentionData($source): array;
}
