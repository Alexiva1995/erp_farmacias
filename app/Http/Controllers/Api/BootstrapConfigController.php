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
        $subscriptionData = null;

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

            try {
                $centralConn = (string) config('tenancy.database.central_connection', config('database.default'));
                if (\Illuminate\Support\Facades\Schema::connection($centralConn)->hasTable('saas_subscriptions')) {
                    $sub = \App\Models\SaasSubscription::where('tenant_id', (string) $t->id)->first();
                    if ($sub) {
                        $statusEnum = \App\Enums\SaasSubscriptionStatusEnum::tryFrom((string) $sub->status) ?? \App\Enums\SaasSubscriptionStatusEnum::ACTIVE;
                        $subscriptionData = [
                            'license_key' => $sub->license_key,
                            'plan_code' => $sub->plan_code,
                            'status' => $statusEnum->value,
                            'status_label' => $statusEnum->label(),
                            'can_operate' => $statusEnum->canOperate(),
                            'current_period_ends_at' => $sub->current_period_ends_at?->format('Y-m-d'),
                            'grace_period_ends_at' => $sub->grace_period_ends_at?->format('Y-m-d'),
                        ];
                    }
                }
            } catch (\Throwable) {
                $subscriptionData = null;
            }
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
                'subscription' => $subscriptionData,
            ]
        ]);
    }
}
