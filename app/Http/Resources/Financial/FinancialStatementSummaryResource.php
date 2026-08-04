<?php

namespace App\Http\Resources\Financial;

use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class FinancialStatementSummaryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $netProfit = $this->resource['net_profit'];

        return [
            'income' => [
                'label'    => 'Ingresos Totales',
                'amount'   => $this->resource['income'],
                'currency' => 'USD',
                'icon'     => 'tabler-trending-up',
                'color'    => 'success',
            ],
            'costs' => [
                'label'    => 'Costos Totales',
                'amount'   => $this->resource['costs'],
                'currency' => 'USD',
                'icon'     => 'tabler-package',
                'color'    => 'warning',
            ],
            'expenses' => [
                'label'    => 'Gastos Operativos',
                'amount'   => $this->resource['expenses'],
                'currency' => 'USD',
                'icon'     => 'tabler-activity',
                'color'    => 'error',
            ],
            'net_profit' => [
                'label'    => 'Utilidad Neta',
                'amount'   => $netProfit,
                'currency' => 'USD',
                'icon'     => $netProfit >= 0 ? 'tabler-pig-money' : 'tabler-chart-down',
                'color'    => $netProfit >= 0 ? 'success' : 'error',
            ],
            'date_range' => [
                'start'           => $this->resource['date_range']['start'],
                'end'             => $this->resource['date_range']['end'],
                'start_formatted' => Carbon::parse($this->resource['date_range']['start'])->format('d/m/Y'),
                'end_formatted'   => Carbon::parse($this->resource['date_range']['end'])->format('d/m/Y'),
            ],
        ];
    }
}
