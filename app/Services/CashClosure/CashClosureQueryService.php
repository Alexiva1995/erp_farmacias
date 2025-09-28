<?php

namespace App\Services\CashClosure;

use Illuminate\Http\Request;
use Illuminate\Database\Eloquent\Builder;
use App\Models\CashClosing;
use function Amp\Dns\query;
use Illuminate\Support\Facades\Auth;
use Illuminate\Database\Eloquent\Relations\Relation; 
use App\Models\Order;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use App\Models\DailyCashClosure;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Collection;
use Illuminate\Support\Carbon;

class CashClosureQueryService
{

     private function getBaseQuery(): Builder
    {
         $sellerId = Auth::id();
        // $sellerId = 2;
        return CashClosing::query()->where('seller_id',$sellerId)->where('status', CashClosing::CLOSED)->with('orders.details.product');
    }


     private function applySorting(Builder $query, ?string $sortBy, string $orderBy): Builder
    {
          if (empty($sortBy)) {
            return $query->orderBy('id', 'desc');
        }

        switch ($sortBy) {
            case 'closing_date':
                return $query->orderBy('closing_date', $orderBy); 
        }

        return $query;
    }

      public function getFilteredQuery(Request $request): Builder
    {

         $query = $this->getBaseQuery();

          $query = $this->applySorting(
            $query, 
            $request->input('sortBy'), 
            $request->input('orderBy', 'desc')
        );

        return $query;
    }


      private function getBaseQueryOrder(): ?CashClosing
    {
        $sellerId = Auth::id();
        // $sellerId = 2;
        return CashClosing::where('seller_id', $sellerId)
                          ->where('status', CashClosing::OPEN)
                          ->first();
    }


     private function applyOrderSorting(Relation $query, ?string $sortBy, string $orderBy): Relation
    {
        if (empty($sortBy)) {
            return $query->orderBy('id', 'desc');
        }
        switch ($sortBy) {
            case 'created_at':
                return $query->orderBy('created_at', $orderBy);
            case 'total_amount':
                return $query->orderBy('total_amount', $orderBy);
            default:
                return $query->orderBy($sortBy, $orderBy);
        }
    }

      public function getFilteredQueryOrder(Request $request): Relation
    {

        $cashClosing = $this->getBaseQueryOrder();
        if (is_null($cashClosing)) {
            throw new ModelNotFoundException('No hay un cierre de caja abierto para este vendedor.');
        }
        $ordersQuery = $cashClosing->orders()->with('client');
        $ordersQuery->where('status', Order::COMPLETED); 
        $ordersQuery = $this->applyOrderSorting(
            $ordersQuery,
            $request->input('sortBy'),
            $request->input('orderBy', 'desc')
        );
        return $ordersQuery;
    }


     private function getBaseQueryDaily(): Builder
    {
        return DailyCashClosure::query()->with('cashClosings');
    }

    private function applySortingDaily(Builder $query, ?string $sortBy, string $orderBy): Builder
    {
          if (empty($sortBy)) {
            return $query->orderBy('id', 'desc');
        }

        switch ($sortBy) {
            case 'total_sales':
                return $query->orderBy('total_sales', $orderBy); 
        }

        return $query;
    }

     public function getFilteredQueryDaily(Request $request): Builder
    {
         $query = $this->getBaseQueryDaily();
          $query = $this->applySortingDaily(
            $query, 
            $request->input('sortBy'), 
            $request->input('orderBy', 'desc')
        );
        return $query;
    }


     private function getBaseQueryMonthly(): Builder
    {
        return DailyCashClosure::query();
    }

     private function applySortingMonthly(Builder $query, ?string $sortBy, string $orderBy): Builder
    {
        if (empty($sortBy)) {
             return $query->orderByDesc('year')->orderByDesc('month');
        }

        switch ($sortBy) {
             case 'total_sales':
                 return $query->orderBy('total_sales_month', $orderBy); 
             case 'period':
                 return $query->orderBy('year', $orderBy)->orderBy('month', $orderBy);
        }

        return $query;
    
    }    
     public function getFilteredQueryMonthly(Request $request): Collection 
    {
        $query = $this->getBaseQueryMonthly();

         $query->select(
            DB::raw('YEAR(created_at) as year'),
            DB::raw('MONTH(created_at) as month'),
            DB::raw('COUNT(id) as days_closed'),
            DB::raw('SUM(total_sales) as total_sales_month'),
            DB::raw('SUM(total_usd) as amount_usd_month'),
            DB::raw('SUM(total_bs) as amount_bs_month'),
            DB::raw('SUM(total_cop) as amount_cop_month')
        )
        ->groupBy('year', 'month');

         $query = $this->applySortingMonthly(
            $query, 
            $request->input('sortBy'), 
            $request->input('orderBy', 'desc')
        );

        $summaries = $query->get();
        $data = new Collection();
        foreach ($summaries as $summary) {

            $endDate = Carbon::create($summary->year, $summary->month)->endOfMonth();

            //$monthName = Carbon::create($summary->year, $summary->month, 1)->monthName;
            $monthName = $endDate->monthName;
            $totalAmountRaw = $summary->amount_usd_month + $summary->amount_bs_month + $summary->amount_cop_month;
            $daysClosed = $summary->days_closed;

            $dailyAverageRaw = ($daysClosed > 0) ? ($summary->total_sales_month / $daysClosed) : 0;

            $object = new \stdClass();
            $object->closing_date = $endDate->format('Y-m-d');

            $object->created_at = $endDate->format('Y-m-d'); 
            $object->period = ucfirst($monthName) . ' ' . $summary->year;
            
            $object->amount_usd = number_format($summary->amount_usd_month, 2, ",", ".");
            $object->amount_bs = number_format($summary->amount_bs_month, 2, ",", ".");
            $object->amount_cop = number_format($summary->amount_cop_month, 0, ",", ".");
            $object->total_amount_raw = $totalAmountRaw;
            $object->total_amount = number_format($totalAmountRaw, 2, ",", ".");
            
            $object->days_closed = $daysClosed;
            $object->daily_average_raw = $dailyAverageRaw;
            $object->daily_average = number_format($dailyAverageRaw, 2, ",", ".");

            $data->push($object);
        }
        return $data;
    }


     private function getBaseQuerySellerCash(): Builder
    {
        return CashClosing::query()->with('orders.details.product','seller');
    }

    private function applyFilters(Builder $query, array $filters): Builder
    {

        if (!empty($filters['q'])) {
            $searchTerm = "%{$filters['q']}%";

            $query->where(function ($subQuery) use ($searchTerm) {
                $subQuery->orWhereHas('seller', function ($sellerQuery) use ($searchTerm) {
                    $sellerQuery->where('username', 'like', $searchTerm);
                });
            });
        }

        if (!empty($filters['start_date']) || !empty($filters['end_date'])) {
            $startDate = !empty($filters['start_date']) ? Carbon::parse($filters['start_date'])->startOfDay() : null;
            $endDate = !empty($filters['end_date']) ? Carbon::parse($filters['end_date'])->endOfDay() : null;

            if ($startDate && $endDate) {
                $query->whereBetween('closing_date', [$startDate, $endDate]);
            } elseif ($startDate) {
                $query->where('closing_date', '>=', $startDate);
            } elseif ($endDate) {
                $query->where('closing_date', '<=', $endDate);
            }
        }

        return $query;
    }

      private function applySortingSellerCash(Builder $query, ?string $sortBy, string $orderBy): Builder
    {
        if (empty($sortBy)) {
            return $query->orderBy('id', 'desc');
        }

        switch ($sortBy) {
            case 'total_sales':
                return $query->orderBy('total_sales', $orderBy);
            case 'total_usd':
                return $query->orderBy('total_usd', $orderBy);
            case 'total_cop':
                return $query->orderBy('total_cop', $orderBy);
            case 'total_bs':
                return $query->orderBy('total_bs', $orderBy);
            case 'status':
                return $query->orderBy('status', $orderBy);
        }

        return $query;
    }

     public function getFilteredQuerySellerCash(Request $request): Builder
    {
        $query = $this->getBaseQuerySellerCash();
        $filters = [
            'q' => $request->q,
            'start_date' => $request->start_date,
            'end_date' => $request->end_date,
        ];
        $this->applyFilters($query, $filters);
        $this->applySortingSellerCash($query, $request->input('sortBy'), $request->input('orderBy', 'asc'));
        return $query;
    }
}
