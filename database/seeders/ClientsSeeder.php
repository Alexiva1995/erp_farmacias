<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\File;
use App\Models\Client;

class ClientsSeeder extends Seeder
{
    /**
     * Map identification type from single letter to full format
     */
    private function mapIdentificationType($type)
    {
        $mapping = [
            'V' => Client::IDENTIFICATION_TYPE_VENEZOLANO,
            'G' => Client::IDENTIFICATION_TYPE_GOBIERNO,
            'E' => Client::IDENTIFICATION_TYPE_EXTRANJERO,
            'J' => Client::IDENTIFICATION_TYPE_JURIDICO,
        ];

        return $mapping[$type] ?? Client::IDENTIFICATION_TYPE_VENEZOLANO;
    }

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $json = File::get(database_path('data/clients.json'));
        $data = json_decode($json, true);

        $chunkSize = 1000;
        $chunks = array_chunk($data, $chunkSize);

        foreach ($chunks as $chunk) {
            $mappedData = [];

            foreach ($chunk as $client) {
                $mappedData[] = [
                    'id' => $client['id'] ?? null,
                    'name' => $client['nombre'] ?? '',
                    'last_name' => $client['apellido'] ?? '',
                    'identification' => $client['cedula'] ?? '',
                    'identification_type' => $this->mapIdentificationType($client['identity_type'] ?? 'V'),
                    'email' => $client['correo'],
                    'phone' => $client['telefono'] ?? '',
                    'address' => $client['direccion'] ?? '',
                    'balance' => $client['balance'] ?? 0,
                    'created_at' => $client['created_at'] ?? now(),
                    'updated_at' => $client['updated_at'] ?? now(),
                ];
            }

            \DB::table('clients')->insertOrIgnore($mappedData);
        }
    }
}
