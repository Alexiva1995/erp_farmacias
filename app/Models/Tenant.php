<?php

declare(strict_types=1);

namespace App\Models;

use Stancl\Tenancy\Contracts\TenantWithDatabase;
use Stancl\Tenancy\Database\Concerns\HasDatabase;
use Stancl\Tenancy\Database\Concerns\HasDomains;
use Stancl\Tenancy\Database\Models\Tenant as BaseTenant;

class Tenant extends BaseTenant implements TenantWithDatabase
{
    use HasDatabase, HasDomains;

    protected $keyType = 'string';
    public $incrementing = false;

    public function getIncrementing(): bool
    {
        return false;
    }

    public function getKeyType(): string
    {
        return 'string';
    }

    public static function getCustomColumns(): array
    {
        return [
            'id',
            'parent_tenant_id',
            'company_name',
            'branch_code',
            'branch_address',
            'branch_phone',
            'can_create_branches',
            'max_branches',
            'is_branch_active',
            'vertical',
            'plan_id',
            'created_at',
            'updated_at',
        ];
    }

    public function parentTenant()
    {
        return $this->belongsTo(self::class, 'parent_tenant_id');
    }

    public function branches()
    {
        return $this->hasMany(self::class, 'parent_tenant_id');
    }

    /**
     * Obtiene todos los tenants del mismo grupo corporativo (casa matriz + sucursales).
     */
    public function getCorporateGroupTenants()
    {
        $rootId = $this->parent_tenant_id ?: $this->id;
        return self::where('id', $rootId)
            ->orWhere('parent_tenant_id', $rootId)
            ->get();
    }
}
