<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

class BootstrapConfigController extends Controller
{
    /**
     * Devuelve la configuración de arranque de módulos y tipo de negocio del ERP.
     */
    public function index(): JsonResponse
    {
        $businessType = \App\Models\GeneralSetting::first()?->business_type ?? env('BUSINESS_TYPE', 'pharmacy');
        $enabledModules = \App\Providers\ModuleServiceProvider::getEnabledModules();

        $isTenant = function_exists('tenancy') && tenancy()->initialized;

        $tenantVertical = null;
        if ($isTenant && tenancy()->tenant) {
            $tenantVertical = tenancy()->tenant->vertical;
        }

        $vertical = $tenantVertical ?: $businessType;

        $currentBranch = null;
        $canCreateBranches = false;
        if ($isTenant && tenancy()->tenant) {
            $t = tenancy()->tenant;
            $currentBranch = [
                'id' => $t->id,
                'parent_tenant_id' => $t->parent_tenant_id,
                'company_name' => $t->company_name ?? ucfirst($t->id),
                'branch_code' => $t->branch_code ?? ($t->parent_tenant_id ? strtoupper($t->id) : 'MATRIZ'),
                'is_matriz' => empty($t->parent_tenant_id),
            ];
            $canCreateBranches = (bool) ($t->can_create_branches ?? false);
        }

        return response()->json([
            'status' => 'success',
            'data' => [
                'business_type' => $businessType,
                'vertical' => $vertical,
                'enabled_modules' => $enabledModules,
                'is_tenant' => $isTenant,
                'is_master' => !$isTenant,
                'current_branch' => $currentBranch,
                'can_create_branches' => $canCreateBranches,
            ]
        ]);
    }
}
