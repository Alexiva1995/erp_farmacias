<?php

namespace App\Contracts;

use App\Models\Retention as MRetention;
use Illuminate\Pagination\LengthAwarePaginator;

interface Retention
{
    public function getInvoicesWithTax(array $filters, int $perPage): LengthAwarePaginator;
    public function generateRetentions(array $invoiceIds, ?string $retentionDate = null): MRetention;
    public function getGeneratedRetentions(array $filters, int $perPage): LengthAwarePaginator;
    public function prepareRetentionData($source): array;
    public function generateAllPendingInRange(string $startDate, string $endDate, ?string $retentionDate = null): int;
    public function deleteRetention(int $id): bool;
    public function updateRetention(int $id, array $data): MRetention;
    public function updateRetentionWithInvoices(int $id, array $data, array $invoices): MRetention;
    public function getRetentionWithInvoices(int $id): MRetention;
    public function omitInvoicesUntilDate(string $cutoffDate): int;
    public function restoreOmittedInvoices(): int;
}
