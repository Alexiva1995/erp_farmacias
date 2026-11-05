<?php

declare(strict_types=1);

namespace App\Http\Requests;

/**
 * Registrar el pago de una cuota de gasto fijo.
 * Reutiliza las reglas y el DTO del registro manual de gastos.
 */
class PayFixedExpenseOccurrenceRequest extends CreateExpenseRequest
{
}
