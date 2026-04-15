<?php

namespace App\Services\Identity;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class CNEQueryService
{
    /**
     * Consulta los datos de un ciudadano en el portal del CNE.
     * 
     * @param string $cedula Solo números
     * @param string $nacionalidad V o E
     * @return array|null [nombre, apellido] o null si no se encuentra
     */
    public function search(string $cedula, string $nacionalidad = 'V'): ?array
    {
        try {
            // Limpiar cédula (solo números)
            $cedula = preg_replace('/[^0-9]/', '', $cedula);
            
            $url = "http://www.cne.gob.ve/web/registro_electoral/ce.php?nacionalidad={$nacionalidad}&cedula={$cedula}";

            $response = Http::timeout(10)->get($url);

            if ($response->failed()) {
                return null;
            }

            $html = $response->body();

            // Verificar si el ciudadano está inscrito
            if (Str::contains($html, 'DATOS DEL ELECTOR')) {
                // El nombre suele venir después de "Nombre:"
                // Buscamos el patrón <b>Nombre:</b></td><td><b>NOMBRE COMPLETO</b>
                
                // Limpieza básica del HTML para facilitar el regex
                $html = preg_replace('/\s+/', ' ', $html);

                // Regex para extraer el nombre (según la estructura actual del CNE)
                // Estructura: <td align="left"><b>Nombre:</b></td> <td><b>APELLIDO1 APELLIDO2 NOMBRE1 NOMBRE2</b></td>
                if (preg_match('/Nombre:<\/b><\/td>\s*<td><b>(.*?)<\/b><\/td>/i', $html, $matches)) {
                    $fullName = trim($matches[1]);
                    return $this->splitFullName($fullName);
                }
            }

            return null;
        } catch (\Exception $e) {
            \Log::error("Error consultando CNE para CI {$cedula}: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Divide el nombre completo del CNE en Nombre y Apellido.
     * El CNE entrega: APELLIDO1 APELLIDO2 NOMBRE1 NOMBRE2
     */
    private function splitFullName(string $fullName): array
    {
        $parts = explode(' ', $fullName);
        $count = count($parts);

        // Caso típico: 2 apellidos y 2 nombres
        if ($count >= 4) {
            $lastNames = $parts[0] . ' ' . $parts[1];
            $names = implode(' ', array_slice($parts, 2));
        } elseif ($count === 3) {
            // 2 apellidos y 1 nombre (común) o 1 apellido y 2 nombres
            // En Venezuela el CNE suele poner los apellidos primero.
            $lastNames = $parts[0] . ' ' . $parts[1];
            $names = $parts[2];
        } else {
            // Casos con menos partes
            $lastNames = $parts[0] ?? '';
            $names = $parts[1] ?? '';
        }

        return [
            'name' => mb_convert_case($names, MB_CASE_TITLE, "UTF-8"),
            'last_name' => mb_convert_case($lastNames, MB_CASE_TITLE, "UTF-8"),
        ];
    }
}
