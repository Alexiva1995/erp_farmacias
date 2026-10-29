<?php

declare(strict_types=1);

namespace App\Services\Credits;

use App\Models\Credit;
use App\Models\CreditInstallment;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CasheaCreditService
{
    public function getFilteredQuery(Request $request): Builder
    {
        $query = Credit::query()
            ->where('credit_type', 'cashea')
            ->with(['client', 'order', 'installments' => function ($q) {
                $q->orderBy('installment_number', 'asc');
            }]);

        if ($request->filled('search')) {
            $search = trim((string) $request->input('search'));
            $query->where(function ($sub) use ($search) {
                $sub->where('cashea_order_reference', 'like', "%{$search}%")
                    ->orWhereHas('client', function ($cq) use ($search) {
                        $cq->where('name', 'like', "%{$search}%")
                            ->orWhere('last_name', 'like', "%{$search}%")
                            ->orWhere('identification', 'like', "%{$search}%");
                    });
            });
        }

        if ($request->filled('status')) {
            $status = $request->input('status');
            if ($status === 'Pending') {
                $query->where('status', 'Active')->where('pending_amount', '>', 0);
            } elseif ($status === 'Paid') {
                $query->where('status', 'Paid');
            }
        }

        $sortBy = $request->input('sortBy', 'created_at');
        $orderBy = $request->input('orderBy', 'desc');

        return $query->orderBy($sortBy, $orderBy);
    }

    public function getInstallmentsSchedule(Request $request)
    {
        $query = CreditInstallment::query()
            ->with(['credit.client', 'credit.order'])
            ->whereHas('credit', function ($q) {
                $q->where('credit_type', 'cashea');
            });

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        if ($request->filled('date_from')) {
            $query->whereDate('due_date', '>=', $request->input('date_from'));
        }

        if ($request->filled('date_to')) {
            $query->whereDate('due_date', '<=', $request->input('date_to'));
        }

        if ($request->filled('search')) {
            $search = trim((string) $request->input('search'));
            $query->where(function ($sub) use ($search) {
                $sub->where('payment_reference', 'like', "%{$search}%")
                    ->orWhereHas('credit.client', function ($cq) use ($search) {
                        $cq->where('name', 'like', "%{$search}%")
                            ->orWhere('last_name', 'like', "%{$search}%")
                            ->orWhere('identification', 'like', "%{$search}%");
                    })
                    ->orWhereHas('credit', function ($cq) use ($search) {
                        $cq->where('cashea_order_reference', 'like', "%{$search}%");
                    });
            });
        }

        $itemsPerPage = (int) $request->input('itemsPerPage', 15);
        return $query->orderBy('due_date', 'asc')->paginate($itemsPerPage);
    }

    public function getSummaryKpis(): array
    {
        $totalFinanced = (float) Credit::where('credit_type', 'cashea')->sum('credit_amount');
        $totalPending = (float) Credit::where('credit_type', 'cashea')->where('status', 'Active')->sum('pending_amount');
        $totalPaid = (float) ($totalFinanced - $totalPending);

        $now = Carbon::now()->toDateString();
        $in14Days = Carbon::now()->addDays(14)->toDateString();

        $dueNext14Days = (float) CreditInstallment::whereHas('credit', function ($q) {
            $q->where('credit_type', 'cashea');
        })
            ->where('status', 'Pending')
            ->whereBetween('due_date', [$now, $in14Days])
            ->sum('amount_usd');

        $overdueInstallments = (float) CreditInstallment::whereHas('credit', function ($q) {
            $q->where('credit_type', 'cashea');
        })
            ->where('status', 'Pending')
            ->whereDate('due_date', '<', $now)
            ->sum('amount_usd');

        return [
            'total_financed_usd' => round($totalFinanced, 2),
            'total_pending_usd' => round($totalPending, 2),
            'total_paid_usd' => round($totalPaid, 2),
            'due_next_14_days_usd' => round($dueNext14Days, 2),
            'overdue_usd' => round($overdueInstallments, 2),
        ];
    }

    public function payInstallment(CreditInstallment $installment, array $data): CreditInstallment
    {
        return DB::transaction(function () use ($installment, $data) {
            $paidAmount = isset($data['amount_usd']) ? (float) $data['amount_usd'] : (float) $installment->amount_usd;
            $paymentReference = $data['payment_reference'] ?? null;

            $installment->update([
                'paid_amount_usd' => $paidAmount,
                'paid_at' => Carbon::now(),
                'payment_reference' => $paymentReference,
                'status' => 'Paid',
            ]);

            $credit = $installment->credit;
            if ($credit) {
                $credit->pending_amount = max(0, (float) $credit->pending_amount - $paidAmount);
                if ($credit->pending_amount <= 0) {
                    $credit->status = 'Paid';
                    $credit->next_due_date = null;
                } else {
                    $nextInstallment = $credit->installments()
                        ->where('status', 'Pending')
                        ->orderBy('due_date', 'asc')
                        ->first();
                    $credit->next_due_date = $nextInstallment?->due_date;
                }
                $credit->save();
            }

            return $installment->load('credit.client');
        });
    }
}
