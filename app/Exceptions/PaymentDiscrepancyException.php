<?php

namespace App\Exceptions;

use Exception;
use Throwable;

class PaymentDiscrepancyException extends Exception
{
    protected float $netPaid;
    protected float $orderTotal;
    protected string $orderCurrency;

    public function __construct(
        float $netPaid,
        float $orderTotal,
        string $orderCurrency,
        string $message = "",
        int $code = 422,
        ?Throwable $previous = null
    ) {
        $this->netPaid = $netPaid;
        $this->orderTotal = $orderTotal;
        $this->orderCurrency = $orderCurrency;

        if (empty($message)) {
            $message = "Discrepancia detectada: El pago neto (" . round($netPaid, 2) . " {$orderCurrency}) no coincide con el total de la factura (" . round($orderTotal, 2) . " {$orderCurrency}).";
        }
        parent::__construct($message, $code, $previous);
    }

    public function getNetPaid(): float
    {
        return $this->netPaid;
    }

    public function getOrderTotal(): float
    {
        return $this->orderTotal;
    }

    public function getOrderCurrency(): string
    {
        return $this->orderCurrency;
    }

    /**
     * Renderizar la excepción como una respuesta HTTP JSON
     */
    public function render($request): \Illuminate\Http\JsonResponse
    {
        return response()->json([
            'success' => false,
            'error_code' => 'PAYMENT_DISCREPANCY',
            'message' => $this->getMessage(),
            'data' => [
                'net_paid' => $this->netPaid,
                'order_total' => $this->orderTotal,
                'currency' => $this->orderCurrency,
            ]
        ], 422);
    }
}
