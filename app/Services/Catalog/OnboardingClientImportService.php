<?php

declare(strict_types=1);

namespace App\Services\Catalog;

use App\Models\Client;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use PhpOffice\PhpSpreadsheet\IOFactory;

class OnboardingClientImportService
{
    /**
     * Parsea el archivo de listado de clientes y analiza las coincidencias con el ERP.
     */
    public function parseAndAnalyze(string $filePath): array
    {
        @ini_set('memory_limit', '512M');
        @ini_set('max_execution_time', '300');

        $rows = $this->extractRawRows($filePath);
        $parsedClients = $this->structureClientsData($rows);

        return $this->correlateWithExistingClients($parsedClients);
    }

    /**
     * Ejecuta la persistencia de clientes (creación de nuevos y enriquecimiento de existentes).
     */
    public function executeImport(array $clientsPayload): array
    {
        @ini_set('memory_limit', '512M');
        @ini_set('max_execution_time', '600');

        return DB::transaction(function () use ($clientsPayload) {
            $currentUserId = Auth::id();
            $stats = [
                'clients_created'   => 0,
                'clients_updated'   => 0,
                'clients_unchanged' => 0,
                'total_processed'   => count($clientsPayload),
            ];

            foreach ($clientsPayload as $item) {
                $isNew = (bool) ($item['is_new'] ?? false);
                $existingId = !empty($item['existing_id']) ? (int) $item['existing_id'] : null;

                $identType = $item['identification_type'] ?? 'V-';
                $identNumber = trim((string) ($item['identification'] ?? ''));
                $name = trim((string) ($item['name'] ?? ''));
                $lastName = !empty($item['last_name']) ? trim((string) $item['last_name']) : null;
                $phone = $this->sanitizePhone($item['phone'] ?? null);
                $address = $this->sanitizeAddress($item['address'] ?? null);

                if (empty($identNumber) || empty($name)) {
                    continue;
                }

                if ($isNew || !$existingId) {
                    $client = Client::where('identification_type', $identType)
                        ->where('identification', $identNumber)
                        ->first();

                    if (!$client) {
                        Client::create([
                            'identification_type' => $identType,
                            'identification'      => $identNumber,
                            'name'                => $name,
                            'last_name'           => $lastName,
                            'phone'               => $phone,
                            'address'             => $address,
                            'client_type'         => Client::CLIENT_TYPE_NUEVO,
                            'status'              => 1,
                            'user_id'             => $currentUserId,
                            'balance'             => 0.0,
                            'is_spe'              => false,
                        ]);
                        $stats['clients_created']++;
                    } else {
                        $stats['clients_unchanged']++;
                    }
                } else {
                    $client = Client::find($existingId);
                    if ($client) {
                        $needsUpdate = false;
                        $updateData = $item['update_data'] ?? [];

                        if (!empty($updateData['phone']) && empty($client->phone)) {
                            $cleanPhone = $this->sanitizePhone($updateData['phone']);
                            if ($cleanPhone) {
                                $client->phone = $cleanPhone;
                                $needsUpdate = true;
                            }
                        }

                        if (!empty($updateData['address']) && (empty($client->address) || strtoupper($client->address) === 'LOCAL' || strtoupper($client->address) === 'S/N')) {
                            $cleanAddr = $this->sanitizeAddress($updateData['address']);
                            if ($cleanAddr) {
                                $client->address = $cleanAddr;
                                $needsUpdate = true;
                            }
                        }

                        if ($needsUpdate) {
                            $client->updated_by = $currentUserId;
                            $client->save();
                            $stats['clients_updated']++;
                        } else {
                            $stats['clients_unchanged']++;
                        }
                    }
                }
            }

            Log::info('[OnboardingClientImport] Importación de clientes finalizada', $stats);

            return $stats;
        });
    }

    /**
     * Extrae filas de datos del archivo soportado.
     */
    protected function extractRawRows(string $filePath): array
    {
        if (!file_exists($filePath)) {
            throw new \InvalidArgumentException("El archivo de clientes no existe en la ruta: {$filePath}");
        }

        $extension = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
        if ($extension === 'csv' || $extension === 'txt') {
            return $this->parseCsvFile($filePath);
        }

        $spreadsheet = IOFactory::load($filePath);
        $sheet = $spreadsheet->getActiveSheet();
        $highestRow = $sheet->getHighestRow();
        $highestCol = $sheet->getHighestColumn();

        $rows = [];
        for ($r = 1; $r <= $highestRow; $r++) {
            $rowRange = $sheet->rangeToArray("A{$r}:{$highestCol}{$r}", null, true, true, false)[0];
            $rows[] = array_map(function ($val) {
                return $val !== null ? (string) $val : '';
            }, $rowRange);
        }

        return $rows;
    }

    /**
     * Parsea un archivo CSV.
     */
    protected function parseCsvFile(string $filePath): array
    {
        $rows = [];
        $handle = fopen($filePath, 'r');
        if ($handle === false) {
            return [];
        }

        while (($data = fgetcsv($handle, 4096, ',')) !== false) {
            $rows[] = array_map('strval', $data);
        }
        fclose($handle);

        return $rows;
    }

    /**
     * Estructura los clientes crudos deduplicando por número de documento dentro del archivo.
     */
    protected function structureClientsData(array $rows): array
    {
        $clients = [];
        $seenIdent = [];

        foreach ($rows as $row) {
            $nonEmpty = array_values(array_filter(array_map('trim', $row), fn($c) => $c !== ''));
            if (count($nonEmpty) < 2) {
                continue;
            }

            $rowString = implode(' ', $nonEmpty);

            // Ignorar encabezados o metadatos de impresión
            if (
                stripos($rowString, 'Código') !== false ||
                stripos($rowString, 'Listado de Clientes') !== false ||
                stripos($rowString, 'ENSALUD') !== false ||
                stripos($rowString, 'Fecha Impresión') !== false ||
                stripos($rowString, 'Página') !== false ||
                stripos($rowString, 'Período') !== false
            ) {
                continue;
            }

            // Detectar columnas de identificación y nombre
            $extracted = $this->extractClientRow($row);
            if ($extracted === null) {
                continue;
            }

            $uniqueKey = $extracted['identification_type'] . $extracted['identification'];
            if (isset($seenIdent[$uniqueKey])) {
                $existingIdx = $seenIdent[$uniqueKey];
                if (empty($clients[$existingIdx]['phone']) && !empty($extracted['phone'])) {
                    $clients[$existingIdx]['phone'] = $extracted['phone'];
                }
                if (empty($clients[$existingIdx]['address']) && !empty($extracted['address'])) {
                    $clients[$existingIdx]['address'] = $extracted['address'];
                }
                continue;
            }

            $seenIdent[$uniqueKey] = count($clients);
            $clients[] = $extracted;
        }

        return $clients;
    }

    /**
     * Extrae y normaliza los campos de un cliente desde una fila.
     */
    protected function extractClientRow(array $row): ?array
    {
        $rawCode = trim($row[1] ?? '');
        $rawName = trim($row[4] ?? '');
        $rawFiscalId = trim($row[5] ?? '');
        $rawAddress = trim($row[6] ?? '');
        $rawPhone = trim($row[7] ?? '');

        // Fallback si las columnas están desplazadas
        if (empty($rawName)) {
            $nonEmpty = array_values(array_filter(array_map('trim', $row), fn($c) => $c !== ''));
            if (count($nonEmpty) >= 2) {
                $rawCode = $nonEmpty[0];
                $rawName = $nonEmpty[1];
                $rawAddress = $nonEmpty[2] ?? '';
                $rawPhone = $nonEmpty[3] ?? '';
            }
        }

        if (empty($rawName) || strlen($rawName) < 2 || str_starts_with($rawName, '/')) {
            return null;
        }

        $identString = !empty($rawFiscalId) ? $rawFiscalId : $rawCode;
        $parsedIdent = $this->parseIdentification($identString);
        if ($parsedIdent === null) {
            return null;
        }

        // Si la cédula es menor a 10000 probablemente es basura de paginación
        if (strlen($parsedIdent['number']) < 4 && $parsedIdent['type'] === 'V-') {
            return null;
        }

        $nameParts = $this->splitNameAndLastName($rawName, $parsedIdent['type']);

        return [
            'raw_code'            => $rawCode,
            'raw_name'            => $rawName,
            'identification_type' => $parsedIdent['type'],
            'identification'      => $parsedIdent['number'],
            'formatted_ident'     => $parsedIdent['type'] . $parsedIdent['number'],
            'name'                => $nameParts['name'],
            'last_name'           => $nameParts['last_name'],
            'phone'               => $this->sanitizePhone($rawPhone),
            'address'             => $this->sanitizeAddress($rawAddress),
        ];
    }

    /**
     * Parsea y normaliza cédulas y RIFs venezolanos (ej: 'V14954044', 'J507754855', '4059152', '00V9233922').
     */
    protected function parseIdentification(string $rawIdent): ?array
    {
        $clean = strtoupper(trim($rawIdent));
        if (empty($clean)) {
            return null;
        }

        // Remover caracteres extraños salvo letras y números
        $clean = preg_replace('/[^A-Z0-9]/', '', $clean);

        // Detectar si empieza por ceros antes de la letra (ej. 00V9233922)
        if (preg_match('/^0+([VJEG])(\d+)$/', $clean, $m)) {
            return [
                'type'   => $m[1] . '-',
                'number' => ltrim($m[2], '0') ?: $m[2],
            ];
        }

        // Si empieza por V, E, J, G seguido de números
        if (preg_match('/^([VJEG])(\d+)$/', $clean, $m)) {
            return [
                'type'   => $m[1] . '-',
                'number' => ltrim($m[2], '0') ?: $m[2],
            ];
        }

        // Si es puramente numérico (cédula venezolana sin letra)
        if (preg_match('/^\d+$/', $clean)) {
            $num = ltrim($clean, '0') ?: $clean;
            return [
                'type'   => 'V-',
                'number' => $num,
            ];
        }

        return null;
    }

    /**
     * Divide nombre y apellido para personas naturales, o asigna razón social completa para empresas.
     */
    protected function splitNameAndLastName(string $fullName, string $type): array
    {
        $fullName = trim(preg_replace('/\s+/', ' ', $fullName));

        if ($type === 'J-' || $type === 'G-') {
            return [
                'name'      => $fullName,
                'last_name' => null,
            ];
        }

        $tokens = explode(' ', $fullName);
        if (count($tokens) === 1) {
            return [
                'name'      => $tokens[0],
                'last_name' => null,
            ];
        }

        if (count($tokens) === 2) {
            return [
                'name'      => $tokens[0],
                'last_name' => $tokens[1],
            ];
        }

        if (count($tokens) === 3) {
            return [
                'name'      => $tokens[0] . ' ' . $tokens[1],
                'last_name' => $tokens[2],
            ];
        }

        return [
            'name'      => $tokens[0] . ' ' . $tokens[1],
            'last_name' => implode(' ', array_slice($tokens, 2)),
        ];
    }

    /**
     * Correlaciona los clientes del archivo con los existentes en el ERP.
     */
    protected function correlateWithExistingClients(array $parsedClients): array
    {
        $existingClients = Client::select(['id', 'identification_type', 'identification', 'name', 'last_name', 'phone', 'address'])
            ->get();

        $existingByIdent = [];
        foreach ($existingClients as $client) {
            $key = $client->identification_type . ltrim((string) $client->identification, '0');
            $existingByIdent[$key] = $client;
            $numKey = ltrim((string) $client->identification, '0');
            $existingByIdent['NUM_' . $numKey] = $client;
        }

        $matchedList = [];
        $newList = [];

        foreach ($parsedClients as $item) {
            $keyExact = $item['identification_type'] . ltrim($item['identification'], '0');
            $keyNum = 'NUM_' . ltrim($item['identification'], '0');

            $matchedClient = $existingByIdent[$keyExact] ?? $existingByIdent[$keyNum] ?? null;

            if ($matchedClient) {
                $updates = [];
                if (empty($matchedClient->phone) && !empty($item['phone'])) {
                    $updates['phone'] = $item['phone'];
                }
                if ((empty($matchedClient->address) || strtoupper($matchedClient->address) === 'LOCAL' || strtoupper($matchedClient->address) === 'S/N') && !empty($item['address'])) {
                    $updates['address'] = $item['address'];
                }

                $matchedList[] = [
                    'extracted_code'      => $item['raw_code'],
                    'extracted_name'      => $item['name'] . ($item['last_name'] ? ' ' . $item['last_name'] : ''),
                    'extracted_phone'     => $item['phone'],
                    'extracted_address'   => $item['address'],
                    'identification_type' => $item['identification_type'],
                    'identification'      => $item['identification'],
                    'existing_id'         => $matchedClient->id,
                    'existing_name'       => $matchedClient->name . ($matchedClient->last_name ? ' ' . $matchedClient->last_name : ''),
                    'existing_ident'      => $matchedClient->identification_type . $matchedClient->identification,
                    'existing_phone'      => $matchedClient->phone,
                    'existing_address'    => $matchedClient->address,
                    'updates_to_apply'    => $updates,
                ];
            } else {
                $newList[] = [
                    'identification_type' => $item['identification_type'],
                    'identification'      => $item['identification'],
                    'formatted_ident'     => $item['formatted_ident'],
                    'name'                => $item['name'],
                    'last_name'           => $item['last_name'],
                    'full_name'           => $item['name'] . ($item['last_name'] ? ' ' . $item['last_name'] : ''),
                    'phone'               => $item['phone'],
                    'address'             => $item['address'],
                ];
            }
        }

        return [
            'summary' => [
                'total_clients_found' => count($parsedClients),
                'matched_clients'     => count($matchedList),
                'new_clients'         => count($newList),
            ],
            'matched_clients' => $matchedList,
            'new_clients'     => $newList,
        ];
    }

    /**
     * Sanitiza el teléfono.
     */
    protected function sanitizePhone(?string $phone): ?string
    {
        if (empty($phone)) {
            return null;
        }

        $cleaned = trim(preg_replace('/[^\d\+\/\-\s]/', '', $phone));
        if ($cleaned === '' || $cleaned === '0' || $cleaned === '00' || $cleaned === '0000' || strtoupper($phone) === 'S/N') {
            return null;
        }

        return $cleaned;
    }

    /**
     * Sanitiza la dirección.
     */
    protected function sanitizeAddress(?string $address): ?string
    {
        if (empty($address)) {
            return null;
        }

        $cleaned = trim($address);
        if ($cleaned === '' || strtoupper($cleaned) === 'LOCAL' || strtoupper($cleaned) === 'S/N' || strtoupper($cleaned) === 'S/D' || strtoupper($cleaned) === '.') {
            return null;
        }

        return $cleaned;
    }
}
