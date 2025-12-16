<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Services\Invoices\InvoiceActionService;
use App\Services\Invoices\InvoiceQueryService;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class InvoiceController extends Controller
{
    public function __construct(
        private InvoiceActionService $invoiceActionService,
        private InvoiceQueryService $invoiceQueryService
    ) {
    }

    public function index(Request $request)
    {
        if (!$request->has('status')) {
            $request->merge(['status' => ['pending']]);
        }
        $query = $this->invoiceQueryService->getInvoicesQuery($request);

        $perPage = $request->input('itemsPerPage', 10);
        $paginatedResult = $query->paginate($perPage);

        return response()->json([
            'data' => $paginatedResult->items(),
            'total' => $paginatedResult->total(),
        ]);
    }

    public function getDetails(Invoice $invoice)
    {
        $details = $this->invoiceQueryService->getInvoiceDetails($invoice);

        return response()->json(['data' => $details]);
    }

    public function store(Request $request)
    {
        $rules = [
            'supplier_id' => 'required|exists:suppliers,id',
            'invoice_number' => 'required|string|max:50',
            'control_number' => 'required|string|max:50',
            'currency' => ['required', Rule::in(['Bs', 'USD', 'COP'])],
            'exp_date' => 'required|date',
            'payment_date' => 'nullable|date|after_or_equal:received_date',
            'received_date' => 'required|date',
            'discount_rule_id' => 'nullable|exists:discount_rules,id',
            'exempt_amount' => 'nullable|numeric|min:0',
            'taxable_base' => 'nullable|numeric|min:0',
            'tax_amount' => 'nullable|numeric|min:0',
            'total_amount' => 'required|numeric|gt:0',
            'created_invoice_date' => 'required|date',
        ];
        $currency = $request->input('currency');

        if ($currency !== 'USD') {
            $rules['exchange_rate'] = 'required|numeric|gt:0';
            $rules['total_usd'] = 'nullable|numeric|gt:0';
        } else {
            $rules['exchange_rate'] = 'nullable|numeric';
            $rules['total_usd'] = 'nullable|numeric|gt:0';
        }

        $validator = Validator::make($request->all(), $rules);

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        $invoice = $this->invoiceActionService->createInvoice($validator->validated());

        return response()->json([
            'message' => 'Factura registrada con éxito.',
            'invoice' => $invoice
        ], 201);
    }

    public function destroy(Invoice $invoice)
    {
        try {
            $this->invoiceActionService->deleteInvoice($invoice);

            return response()->noContent();

        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 409);
        }
    }

    public function approve(Request $request, Invoice $invoice)
    {
        $rules = [
            'payment_rule_id' => 'nullable|exists:payment_rules,id',
        ];

        $validator = Validator::make($request->all(), $rules);

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        try {
            $approvedInvoice = $this->invoiceActionService->approveInvoice(
                $invoice,
                $validator->validated()
            );

            return response()->json([
                'message' => 'Factura aprobada con éxito.',
                'invoice' => $approvedInvoice
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al aprobar la factura: ' . $e->getMessage()
            ], 500);
        }
    }

    public function reject(Request $request, Invoice $invoice)
    {

        try {
            $rejectedInvoice = $this->invoiceActionService->rejectInvoice(
                $invoice
            );

            return response()->json([
                'message' => 'Factura rechazada con éxito.',
                'invoice' => $rejectedInvoice
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al rechazar la factura: ' . $e->getMessage()
            ], 500);
        }
    }

    public function show(Invoice $invoice)
    {
        $invoiceData = $this->invoiceQueryService->getInvoiceById($invoice);

        return response()->json(['data' => $invoiceData]);
    }

    public function getSuggestedDetails(Invoice $invoice)
    {
        $details = $this->invoiceQueryService->getSuggestedAndExistingDetails($invoice);

        return response()->json(['data' => $details]);
    }

    public function updateData(Request $request, Invoice $invoice)
    {
        $rules = [
            'supplier_id' => 'required|exists:suppliers,id',
            'invoice_number' => 'required|string|max:50',
            'control_number' => 'required|string|max:50',
            'exp_date' => 'required|date',
            'payment_date' => 'nullable|date|after_or_equal:received_date',
            'received_date' => 'required|date',
            'discount_rule_id' => 'nullable|exists:discount_rules,id',
            'exempt_amount' => 'nullable|numeric|min:0',
            'taxable_base' => 'nullable|numeric|min:0',
            'tax_amount' => 'nullable|numeric|min:0',
            'total_amount' => 'required|numeric|gt:0',
            'currency' => ['required', Rule::in(['Bs', 'USD', 'COP'])],
        ];
        $currency = $request->input('currency');

        if ($currency !== 'USD') {
            $rules['exchange_rate'] = 'required|numeric|gt:0';
            $rules['total_usd'] = 'nullable|numeric|gt:0';
        } else {
            $rules['exchange_rate'] = 'nullable|numeric|gt:0';
            $rules['total_usd'] = 'nullable|numeric|gt:0';
        }
        $validator = Validator::make($request->all(), $rules);

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        try {
            $updatedInvoice = $this->invoiceActionService->updateInvoiceData($invoice, $validator->validated());

            return response()->json([
                'message' => 'Datos de la factura actualizados con éxito.',
                'invoice' => $updatedInvoice
            ]);

        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }

    public function saveDetails(Request $request, Invoice $invoice)
    {
        $rules = [
            'invoice' => 'required|array',
            'invoice.supplier_discount_id' => 'nullable|exists:supplier_discounts,id',
            'details' => 'present|array',
            'details.*.product.id' => 'required|integer|exists:products,id',
            'details.*.quantity' => 'required|numeric|min:1',
            'details.*.unit_cost' => 'required|numeric|min:0',
            'details.*.lot_number' => 'required|string|max:100',
            'details.*.expiration_date' => 'required|date',
            'details.*.location' => 'nullable|string|max:100',
            'details.*.tax_enabled' => 'boolean',
            'details.*.is_return' => 'boolean',
        ];

        $messages = [
            'invoice.required' => 'Faltan los datos de la cabecera de la factura.',
            'invoice.supplier_discount_id.exists' => 'El descuento seleccionado no es válido.',

            'details.present' => 'La lista de productos es obligatoria.',
            'details.array' => 'El formato de la lista de productos es incorrecto.',

            'details.*.product.id.exists' => 'Uno de los productos enviados no existe en la base de datos.',

            'details.*.quantity.required' => 'La cantidad es obligatoria para todos los productos.',
            'details.*.quantity.min' => 'La cantidad de los productos debe ser al menos 1.',

            'details.*.unit_cost.required' => 'El costo es obligatorio.',
            'details.*.unit_cost.min' => 'El costo no puede ser negativo.',

            'details.*.lot_number.required' => 'El N° de Lote es obligatorio para todos los productos.',
            'details.*.lot_number.max' => 'El N° de Lote es demasiado largo (máx 100 caracteres).',

            'details.*.expiration_date.required' => 'La Fecha de Vencimiento es obligatoria para todos los productos.',
            'details.*.expiration_date.date' => 'El formato de fecha de vencimiento es inválido.',

            'details.*.location.max' => 'La ubicación es demasiado larga.',
        ];

        $validator = Validator::make($request->all(), $rules, $messages);

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        try {
            $updatedInvoice = $this->invoiceActionService->saveInvoiceDetails($invoice, $validator->validated());
            return response()->json([
                'message' => 'Progreso de la factura guardado con éxito.',
                'invoice' => $updatedInvoice
            ]);
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }

    public function finalize(Request $request, Invoice $invoice)
    {
        try {
            $finalizedInvoice = $this->invoiceActionService->finalizeInvoice($invoice);

            return response()->json([
                'message' => 'Factura finalizada con éxito.',
                'invoice' => $finalizedInvoice
            ]);
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }

    public function updateLocations(Request $request, Invoice $invoice)
    {

        try {
            $updatedInvoice = $this->invoiceActionService->updateInvoiceLocations($invoice, $request->all());

            return response()->json([
                'message' => 'Ubicaciones actualizadas con éxito.',
                'invoice' => $updatedInvoice
            ]);

        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }

    public function getSupplierDebts(Request $request)
    {
        $supplierDebts = $this->invoiceQueryService->calculateSupplierDebts();

        return response()->json([
            'data' => [
                'total_debts' => $supplierDebts,
                'currency' => 'USD',
                'calculated_at' => now()->toISOString(),
                'description' => 'Facturas pendientes de pago a proveedores'
            ],
            'message' => 'Deudas con proveedores calculadas con éxito.'
        ], 200);
    }

    public function returnInvoiceToPendingStatus(Invoice $invoice)
    {
        $response = $this->invoiceActionService->updateToPendingStatus($invoice);

        return response()->json([
            'status' => $response['status'],
            'message' => $response['message'] != null
                ? $response['message']
                : ($response['status']
                    ? 'Se devolvió la factura a pendientes'
                    : 'No se pudo devolver la factura a pendientes')
        ], 200);
    }
}
