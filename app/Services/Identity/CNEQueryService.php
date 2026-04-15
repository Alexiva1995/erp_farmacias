<?php

namespace App\Services\Identity;

use App\Models\Client as ClientModel;
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
            
            $appId = env('CEDULA_API_APP_ID');
            $token = env('CEDULA_API_TOKEN');

            // Nueva fuente oficial según Wiki: api.cedula.com.ve
            $url = "https://api.cedula.com.ve/api/v1?app_id={$appId}&token={$token}&cedula={$cedula}";

            $response = Http::timeout(10)->get($url);

            if ($response->failed()) {
                \Log::warning("Falla en API oficial cedula.com.ve para CI {$cedula}: " . $response->status());
                return null;
            }

            $data = $response->json();

            // Verificar estructura oficial { error: false, data: { ... } }
            if (isset($data['error']) && !$data['error'] && isset($data['data']) && $data['data']) {
                $elector = $data['data'];
                
                // Construir nombres según la estructura de la api oficial
                $names = trim(($elector['primer_nombre'] ?? '') . ' ' . ($elector['segundo_nombre'] ?? ''));
                $lastNames = trim(($elector['primer_apellido'] ?? '') . ' ' . ($elector['segundo_apellido'] ?? ''));

                return [
                    'name' => mb_convert_case($names, MB_CASE_TITLE, "UTF-8"),
                    'last_name' => mb_convert_case($lastNames, MB_CASE_TITLE, "UTF-8"),
                ];
            }

            return null;
        } catch (\Exception $e) {
            \Log::error("Error consultando API Oficial para CI {$cedula}: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Procesa un lote de clientes para verificar sus identidades.
     */
    public function verifyBatch(int $limit = 10): array
    {
        $clients = ClientModel::where('identification_type', 'V-')
            ->where('identification', 'regexp', '^[0-9]+$')
            ->whereNull('cne_verified_at') // Solo los que no han sido verificados satisfactoriamente
            ->orderBy('id', 'asc') // Procesar por orden de creación para ser más predecible
            ->limit($limit)
            ->get();

        $updatedCount = 0;
        $notFoundCount = 0;

        foreach ($clients as $client) {
            $data = $this->search($client->identification);
            if ($data) {
                $client->update([
                    'name' => $data['name'],
                    'last_name' => $data['last_name'],
                    'cne_verified_at' => now(), // Marcamos como verificado
                ]);
                $updatedCount++;
            } else {
                // Si no se encuentra, marcamos de todas formas la fecha para 
                // que el sistema no se quede atascado intentando con el mismo registro.
                $client->update(['cne_verified_at' => now()]);
                $notFoundCount++;
            }
            
            usleep(500000); // 0.5s delay
        }

        return [
            'updated' => $updatedCount,
            'not_found' => $notFoundCount,
        ];
    }
}
