<?php

namespace App\Http\Requests\Fiscal;

use Illuminate\Foundation\Http\FormRequest;

class FiscalZReportFilterRequest extends FormRequest
{
    /**
     * Determina si el usuario está autorizado para realizar la solicitud.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Reglas de validación para filtros de Reporte Z.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'q'            => ['nullable', 'string', 'max:100'],
            'startDate'    => ['nullable', 'date'],
            'endDate'      => ['nullable', 'date', 'after_or_equal:startDate'],
            'page'         => ['nullable', 'integer', 'min:1'],
            'itemsPerPage' => ['nullable', 'integer', 'min:-1', 'max:100'],
            'sortBy'       => ['nullable', 'string', 'in:id,report_number,report_date,exempt_amount,base_16_amount,iva_amount,igtf_base_amount,igtf_amount,total_amount,invoices_count,status'],
            'orderBy'      => ['nullable', 'string', 'in:asc,desc,ASC,DESC'],
        ];
    }
}
