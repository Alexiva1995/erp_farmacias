<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Services\Invoices\InvoiceActionService;
use App\Services\Invoices\InvoiceQueryService;
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

    public function indexForOrder(Request $request)
    {
        $query = $this->invoiceQueryService->getForOrderQuery($request);

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
            'payment_date' => 'nullable|date|after_or_equal:exp_date',
            'received_date' => 'required|date',
            'discount_rule_id' => 'nullable|exists:discount_rules,id',
            'exempt_amount' => 'nullable|numeric|min:0',
            'taxable_base' => 'nullable|numeric|min:0',
            'tax_amount' => 'nullable|numeric|min:0',
            'total_amount' => 'required|numeric|gt:0',
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
            'supplier_discount_id' => 'nullable|exists:supplier_discounts,id',
            'payment_rule_id' => 'nullable|exists:payment_rules,id',
            'return_item_ids' => 'nullable|array',
            'return_item_ids.*' => 'exists:invoice_details,id'
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
        $rules = [
            'reason' => 'required|string|max:500'
        ];

        $validator = Validator::make($request->all(), $rules);

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        try {
            $rejectedInvoice = $this->invoiceActionService->rejectInvoice(
                $invoice,
                $validator->validated()['reason']
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
            'payment_date' => 'nullable|date|after_or_equal:exp_date',
            'received_date' => 'required|date',
            'discount_rule_id' => 'nullable|exists:discount_rules,id',
            'exempt_amount' => 'nullable|numeric|min:0',
            'taxable_base' => 'nullable|numeric|min:0',
            'tax_amount' => 'nullable|numeric|min:0',
            'total_amount' => 'required|numeric|gt:0',
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

    public function update(Request $request, Invoice $invoice)
    {
        $rules = [
            'invoice' => 'required|array',
            'invoice.control_number' => 'required|string|max:50',
            'invoice.invoice_number' => 'required|string|max:50',
            'invoice.exp_date' => 'required|date',

            'details' => 'present|array',
            'details.*.product.id' => 'required|integer|exists:products,id',
            'details.*.quantity' => 'required|numeric|min:1',
            'details.*.unit_cost' => 'required|numeric|min:0',
            'details.*.lot_number' => 'required|string|max:100',
            'details.*.expiration_date' => 'required|date',
            'details.*.location' => 'required|string|max:100',
        ];

        $validator = Validator::make($request->all(), $rules);

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        $validatedData = $validator->validated();

        try {
            $updatedInvoice = $this->invoiceActionService->updateInvoice($invoice, $validatedData);

            return response()->json([
                'message' => 'Factura finalizada y actualizada con éxito.',
                'invoice' => $updatedInvoice
            ]);

        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }
    public function indexForLocation(Request $request)
    {
        $query = $this->invoiceQueryService->getForLocationQuery($request);

        $perPage = $request->input('itemsPerPage', 10);
        $paginatedResult = $query->paginate($perPage);

        return response()->json([
            'data' => $paginatedResult->items(),
            'total' => $paginatedResult->total(),
        ]);
    }

    public function updateLocations(Request $request, Invoice $invoice)
    {
        $rules = [
            'details' => 'required|array',
            'details.*.id' => 'required|integer|exists:invoice_details,id',
            'details.*.location' => 'required|string|max:100',
        ];

        $validator = Validator::make($request->all(), $rules);

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        try {
            $updatedInvoice = $this->invoiceActionService->updateInvoiceLocations($invoice, $validator->validated());

            return response()->json([
                'message' => 'Ubicaciones actualizadas con éxito.',
                'invoice' => $updatedInvoice
            ]);

        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }
    public function indexOrdered(Request $request)
    {
        $query = $this->invoiceQueryService->getForApprovalQuery($request);

        $perPage = $request->input('itemsPerPage', 10);
        $paginatedResult = $query->paginate($perPage);

        return response()->json([
            'data' => $paginatedResult->items(),
            'total' => $paginatedResult->total(),
        ]);
    }
}
