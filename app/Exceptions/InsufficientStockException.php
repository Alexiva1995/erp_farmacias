<?php

namespace App\Exceptions;

use Exception;
use Throwable;

class InsufficientStockException extends Exception
{
    protected $availableStock;
    protected $requestedQuantity;
    protected $productName;

    public function __construct(
        string $productName,
        int
        $availableStock,
        int $requestedQuantity,
        string $message = "",
        int $code = 0,
        ?Throwable $previous = null
    ) {
        $this->productName = $productName;
        $this->availableStock = $availableStock;
        $this->requestedQuantity = $requestedQuantity;

        if (empty($message)) {
            $message = "Stock insuficiente para '{$productName}'. Disponible: {$availableStock}, Solicitado: {$requestedQuantity}.";
        }
        parent::__construct($message, $code, $previous);
    }


    public function getAvailableStock(): int
    {
        return $this->availableStock;
    }

    public function getRequestedQuantity(): int
    {
        return $this->requestedQuantity;
    }

    public function getProductName(): string
    {
        return $this->productName;
    }

    /**
     * Renderizar la excepción como una respuesta HTTP JSON
     */
    public function render($request): \Illuminate\Http\JsonResponse
    {
        return response()->json([
            'success' => false,
            'error_code' => 'INSUFFICIENT_STOCK',
            'message' => $this->getMessage(),
            'data' => [
                'product_name' => $this->productName,
                'available_stock' => $this->availableStock,
                'requested_quantity' => $this->requestedQuantity,
            ]
        ], 422);
    }
}
