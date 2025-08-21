<?php


namespace App\Contracts;

use App\Exports\AssistantReportProductExport;
use App\Exports\StockProductExport;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;

interface Product
{

    public function consultProduct(): Collection;
    public function filtrarStock(array $filtros): LengthAwarePaginator;
    public function filtrarStockWithoutPaginate(array $filtros): Collection;
    public function exportExcel(array $filtros): StockProductExport;
    public function filtrarIaOrderAssistantTypeAverage(array $filtros): LengthAwarePaginator;
    public function filtrarIaOrderAssistantTypeAverageWithoutPaginate(array $filtros): Collection;
    public function filtrarIaOrderAssistantTypeSales(array $filtros): LengthAwarePaginator;
    public function filtrarIaOrderAssistantTypeSalesWithoutPaginate(array $filtros): Collection;
    public function filtrarIndividualProductForAssistantReportTypeAveragesWithPaginate(array $filtros): LengthAwarePaginator;
    public function filtrarIndividualProductForAssistantReportTypeAveragesWithoutPaginate(array $filtros): Collection;
    public function filtrarIndividualProductForAssistantReportTypeSalesWithPaginate(array $filtros): LengthAwarePaginator;
    public function filtrarIndividualProductForAssistantReportTypeSalesWithoutPaginate(array $filtros): Collection;
    public function exportAssistantReportExcel(array $filtros): AssistantReportProductExport;
}
