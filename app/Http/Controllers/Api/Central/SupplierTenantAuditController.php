<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Central;

use App\Contracts\Suppliers\SupplierAuditServiceInterface;
use App\Http\Controllers\Controller;
use App\Http\Requests\Suppliers\SupplierAuditRequest;
use App\Http\Resources\SupplierAuditResource;
use Illuminate\Http\JsonResponse;

class SupplierTenantAuditController extends Controller
{
    public function __construct(
        protected SupplierAuditServiceInterface $auditService
    ) {}

    /**
     * Retorna la auditoría consolidada de un proveedor a través de todos los tenants
     */
    public function index(SupplierAuditRequest $request): SupplierAuditResource|JsonResponse
    {
        $validated = $request->validated();
        $supplierId = isset($validated['supplier_id']) ? (int) $validated['supplier_id'] : 1016;
        $month = isset($validated['month']) ? (string) $validated['month'] : null;

        $auditData = $this->auditService->getSupplierMultiTenantAudit($supplierId, $month);
        $auditData['suppliers'] = $this->auditService->getAvailableSuppliers();

        return new SupplierAuditResource($auditData);
    }
}
