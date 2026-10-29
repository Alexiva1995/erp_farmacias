<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Wholesale;

use App\Enums\PaymentVerificationStatusEnum;
use App\Http\Controllers\Controller;
use App\Models\Credit;
use App\Models\CreditPayment;
use App\Services\Tenancy\InterTenantB2BBridgeService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CollectionConciliationController extends Controller
{
    public function __construct(
        protected InterTenantB2BBridgeService $interTenantBridge
    ) {}

    /**
     * Bandeja de pagos y abonos pendientes por conciliar (desde Marketplace o sincronización ERP-a-ERP).
     */
    public function index(Request $request): JsonResponse
    {
        $status = $request->input('status', PaymentVerificationStatusEnum::PENDING_VERIFICATION->value);
        $perPage = (int) $request->input('per_page', 25);

        $query = CreditPayment::query()
            ->with(['client:id,name,identification,sales_rep_id,collector_user_id', 'allocations.credit', 'verifiedBy:id,name'])
            ->where('status', $status);

        if ($request->filled('collector_user_id')) {
            $collectorId = (int) $request->collector_user_id;
            $query->whereHas('client', function ($q) use ($collectorId) {
                $q->where('collector_user_id', $collectorId);
            });
        }

        if ($request->filled('source_channel')) {
            $query->where('source_channel', $request->source_channel);
        }

        if ($request->filled('search')) {
            $search = trim((string) $request->search);
            $query->where(function ($q) use ($search) {
                $q->where('reference_number', 'like', "%{$search}%")
                  ->orWhereHas('client', function ($cq) use ($search) {
                      $cq->where('name', 'like', "%{$search}%")
                         ->orWhere('identification', 'like', "%{$search}%");
                  });
            });
        }

        $payments = $query->orderBy('created_at', 'asc')->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => $payments,
        ]);
    }

    /**
     * Aprueba y concilia un pago o abono, rebajando el saldo de los créditos.
     */
    public function approve(int $paymentId, Request $request): JsonResponse
    {
        $payment = CreditPayment::with(['allocations.credit', 'client'])->findOrFail($paymentId);

        if ($payment->status === PaymentVerificationStatusEnum::APPROVED->value) {
            throw ValidationException::withMessages([
                'payment' => 'Este pago ya ha sido aprobado y conciliado.',
            ]);
        }

        $verifierId = (int) (auth()->id() ?: 1);

        DB::transaction(function () use ($payment, $verifierId) {
            $payment->update([
                'status' => PaymentVerificationStatusEnum::APPROVED->value,
                'verified_by' => $verifierId,
                'verified_at' => Carbon::now(),
                'rejection_reason' => null,
            ]);

            // Descontar saldo de cada crédito asignado
            if ($payment->allocations->isNotEmpty()) {
                foreach ($payment->allocations as $allocation) {
                    $credit = $allocation->credit;
                    if ($credit) {
                        $deductUSD = (float) $allocation->amount_allocated_usd;
                        $earlyDiscount = (float) $allocation->early_payment_discount_applied;

                        $credit->pending_amount = max(0, (float) $credit->pending_amount - ($deductUSD + $earlyDiscount));
                        if ($credit->pending_amount <= 0.01) {
                            $credit->status = 'Paid';
                            $credit->pending_amount = 0;
                        }
                        $credit->save();
                    }
                }
            } else {
                // Si no tiene allocations directas, aplicar método FIFO a las facturas vencidas del cliente
                $credits = Credit::where('client_id', $payment->client_id)
                    ->where('status', 'Active')
                    ->where('pending_amount', '>', 0)
                    ->orderBy('next_due_date', 'asc')
                    ->get();

                $remainingUSD = (float) $payment->amount_usd_equivalent;

                foreach ($credits as $credit) {
                    if ($remainingUSD <= 0) {
                        break;
                    }

                    $pending = (float) $credit->pending_amount;
                    $applied = min($remainingUSD, $pending);

                    $credit->pending_amount = max(0, $pending - $applied);
                    if ($credit->pending_amount <= 0.01) {
                        $credit->status = 'Paid';
                        $credit->pending_amount = 0;
                    }
                    $credit->save();

                    $remainingUSD -= $applied;
                }
            }

            // Sincronizar confirmación con el tenant de la Farmacia si provino de interconexión ERP
            if ($payment->source_channel === 'erp_tenant_sync') {
                $this->interTenantBridge->confirmPaymentVerificationInPharmacy($payment);
            }
        });

        return response()->json([
            'success' => true,
            'message' => 'Pago aprobado y conciliado con éxito en la cartera del cliente.',
            'data' => $payment->fresh(),
        ]);
    }

    /**
     * Rechaza un pago con justificación.
     */
    public function reject(int $paymentId, Request $request): JsonResponse
    {
        $request->validate([
            'reason' => 'required|string|min:5|max:500',
        ]);

        $payment = CreditPayment::findOrFail($paymentId);
        $reason = (string) $request->reason;
        $verifierId = (int) (auth()->id() ?: 1);

        $payment->update([
            'status' => PaymentVerificationStatusEnum::REJECTED->value,
            'verified_by' => $verifierId,
            'verified_at' => Carbon::now(),
            'rejection_reason' => $reason,
        ]);

        // Notificar rechazo en el tenant de la Farmacia
        if ($payment->source_channel === 'erp_tenant_sync') {
            $this->interTenantBridge->rejectPaymentVerificationInPharmacy($payment, $reason);
        }

        return response()->json([
            'success' => true,
            'message' => 'El pago ha sido rechazado y se ha notificado a la farmacia.',
            'data' => $payment,
        ]);
    }
}
