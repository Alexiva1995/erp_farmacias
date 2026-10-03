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

        // Si ya está inicializado, continuar
        if (tenancy()->initialized) {
            return $next($request);
        }

        // Buscar el tenant asociado al dominio/subdominio
        $domainRecord = Domain::where('domain', $host)->first();
        if ($domainRecord && $domainRecord->tenant) {
            tenancy()->initialize($domainRecord->tenant);

            // Garantizar que Sanctum reconozca este dominio/subdominio como stateful para cookies de sesión
            $currentStateful = config('sanctum.stateful', []);
            if (!in_array($host, $currentStateful, true)) {
                config(['sanctum.stateful' => array_merge($currentStateful, [$host, "{$host}:*"])]);
            }
        }

        return $next($request);
    }
}
