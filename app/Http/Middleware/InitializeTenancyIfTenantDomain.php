<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Stancl\Tenancy\Database\Models\Domain;
use Symfony\Component\HttpFoundation\Response;

class InitializeTenancyIfTenantDomain
{
    /**
     * Maneja la petición entrante para inicializar Tenancy solo si el dominio corresponde a un tenant.
     *
     * @param \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response) $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $host = $request->getHost();
        $centralDomains = config('tenancy.central_domains', ['127.0.0.1', 'localhost']);

        // Si es un dominio central, continuar en contexto central sin alterar nada
        if (in_array($host, $centralDomains, true)) {
            return $next($request);
        }

        try {
            $subdomain = explode('.', $host)[0];
            $subdomainUnderscore = str_replace('-', '_', $subdomain);
            $subdomainDash = str_replace('_', '-', $subdomain);
            $tenant = null;

            // 1. Buscar en tabla Domain
            $domainRecord = Domain::where('domain', $host)
                ->orWhere('domain', $subdomain)
                ->orWhere('tenant_id', $subdomain)
                ->orWhere('tenant_id', $subdomainUnderscore)
                ->orWhere('tenant_id', $subdomainDash)
                ->first();

            if ($domainRecord && $domainRecord->tenant) {
                $tenant = $domainRecord->tenant;
            }

            // 2. Buscar directamente en tabla Tenant
            if (!$tenant) {
                $tenant = \App\Models\Tenant::find($subdomain)
                    ?? \App\Models\Tenant::find($subdomainUnderscore)
                    ?? \App\Models\Tenant::find($subdomainDash)
                    ?? \App\Models\Tenant::find($host);
            }

            // 3. Si no existe registro pero es un subdominio de tenant, auto-conciliarlo
            if (!$tenant && $subdomain !== 'www' && !empty($subdomain)) {
                $tenant = \App\Models\Tenant::firstOrCreate(
                    ['id' => $subdomain],
                    ['company_name' => ucwords(str_replace(['-', '_'], ' ', $subdomain))]
                );
                Domain::firstOrCreate(['domain' => $host], ['tenant_id' => $tenant->id]);
            }

            if ($tenant) {
                tenancy()->initialize($tenant);

                // Garantizar que Sanctum reconozca este dominio/subdominio como stateful para cookies de sesión
                $currentStateful = config('sanctum.stateful', []);
                if (!in_array($host, $currentStateful, true)) {
                    config(['sanctum.stateful' => array_merge($currentStateful, [$host, "{$host}:*"])]);
                }
            }
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error("[InitializeTenancyIfTenantDomain] Error: " . $e->getMessage());
        }

        return $next($request);
    }
}
