<?php

namespace App\Exceptions;

use Exception;
use Throwable;

class InvalidLoanException extends Exception
{
    protected array $errors = [];

    public function __construct(string $message = "", int $code = 400, ?Throwable $previous = null, array $errors = [])
    {
        $this->errors = $errors;
        if (empty($message)) {
            $message = "Los datos del préstamo suministrados son inválidos.";
        }
        parent::__construct($message, $code, $previous);
    }

    public function getErrors(): array
    {
        return $this->errors;
    }

    /**
     * Renderizar la excepción como una respuesta HTTP JSON
     */
    public function render($request): \Illuminate\Http\JsonResponse
    {
        return response()->json([
            'success' => false,
            'error_code' => 'INVALID_LOAN_DATA',
            'message' => $this->getMessage(),
            'errors' => $this->errors
        ], 422);
    }
}
