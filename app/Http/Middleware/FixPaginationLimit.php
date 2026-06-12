<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class FixPaginationLimit
{
    /**
     * Maneja una petición entrante.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function handle(Request $request, Closure $next): Response
    {
        $paginationKeys = ['per_page', 'perPage', 'items_per_page', 'itemsPerPage'];

        foreach ($paginationKeys as $key) {
            if ($request->has($key)) {
                $value = $request->input($key);
                if (is_numeric($value) && (int) $value <= 0) {
                    $request->merge([$key => 999999]);
                }
            }
        }

        return $next($request);
    }
}
