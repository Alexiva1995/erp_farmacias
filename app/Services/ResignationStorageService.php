<?php

namespace App\Services;

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;

class ResignationStorageService
{
    private const STORAGE_FILE = 'resignations.json';
    private const STORAGE_DISK = 'local';

    /**
     * Guardar una nueva renuncia en el archivo JSON
     */
    public function storeResignation(array $resignationData): bool
    {
        try {
            $resignations = $this->getAllResignations();

            // Verificar si ya existe una renuncia para este empleado
            foreach ($resignations as $existingResignation) {
                if ($existingResignation['employee_id'] == $resignationData['employee_id']) {
                    Log::warning('Ya existe una renuncia para este empleado', [
                        'employee_id' => $resignationData['employee_id'],
                        'employee_name' => $resignationData['employee_name']
                    ]);
                    return false; // No crear duplicados
                }
            }

            // Generar ID único
            $resignationData['id'] = $this->generateUniqueId();
            $resignationData['created_at'] = now()->toISOString();
            $resignationData['updated_at'] = now()->toISOString();

            $resignations[] = $resignationData;

            return $this->saveResignations($resignations);
        } catch (\Exception $e) {
            Log::error('Error al guardar renuncia: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Obtener todas las renuncias
     */
    public function getAllResignations(): array
    {
        try {
            if (!Storage::disk(self::STORAGE_DISK)->exists(self::STORAGE_FILE)) {
                return [];
            }

            $content = Storage::disk(self::STORAGE_DISK)->get(self::STORAGE_FILE);
            $data = json_decode($content, true);

            return is_array($data) ? $data : [];
        } catch (\Exception $e) {
            Log::error('Error al leer renuncias: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Obtener renuncias con paginación
     */
    public function getResignationsPaginated(int $page = 1, int $perPage = 10, array $filters = []): array
    {
        $allResignations = $this->getAllResignations();

        // Aplicar filtros
        if (!empty($filters)) {
            $allResignations = $this->applyFilters($allResignations, $filters);
        }

        // Ordenar por fecha de creación (más recientes primero)
        usort($allResignations, function ($a, $b) {
            return strtotime($b['created_at']) - strtotime($a['created_at']);
        });

        $total = count($allResignations);
        $offset = ($page - 1) * $perPage;
        $items = array_slice($allResignations, $offset, $perPage);

        return [
            'data' => $items,
            'total' => $total,
            'per_page' => $perPage,
            'current_page' => $page,
            'last_page' => ceil($total / $perPage),
            'from' => $offset + 1,
            'to' => min($offset + $perPage, $total)
        ];
    }

    /**
     * Obtener una renuncia por ID
     */
    public function getResignationById(int $id): ?array
    {
        $resignations = $this->getAllResignations();

        foreach ($resignations as $resignation) {
            if ($resignation['id'] == $id) {
                return $resignation;
            }
        }

        return null;
    }

    /**
     * Actualizar una renuncia
     */
    public function updateResignation(int $id, array $updateData): bool
    {
        try {
            $resignations = $this->getAllResignations();

            foreach ($resignations as &$resignation) {
                if ($resignation['id'] == $id) {
                    $resignation = array_merge($resignation, $updateData);
                    $resignation['updated_at'] = now()->toISOString();
                    break;
                }
            }

            return $this->saveResignations($resignations);
        } catch (\Exception $e) {
            Log::error('Error al actualizar renuncia: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Eliminar una renuncia
     */
    public function deleteResignation(int $id): bool
    {
        try {
            $resignations = $this->getAllResignations();

            $resignations = array_filter($resignations, function ($resignation) use ($id) {
                return $resignation['id'] != $id;
            });

            return $this->saveResignations(array_values($resignations));
        } catch (\Exception $e) {
            Log::error('Error al eliminar renuncia: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Obtener estadísticas de renuncias
     */
    public function getResignationStats(): array
    {
        $resignations = $this->getAllResignations();

        $stats = [
            'total' => count($resignations),
            'voluntary' => 0,
            'unjustified_dismissal' => 0,
            'this_month' => 0,
            'this_year' => 0
        ];

        $currentMonth = now()->format('Y-m');
        $currentYear = now()->format('Y');

        foreach ($resignations as $resignation) {
            // Contar por tipo
            if ($resignation['resignation_type'] === 'voluntary') {
                $stats['voluntary']++;
            } elseif ($resignation['resignation_type'] === 'unjustified_dismissal') {
                $stats['unjustified_dismissal']++;
            }

            // Contar por período
            $createdAt = date('Y-m', strtotime($resignation['created_at']));
            if ($createdAt === $currentMonth) {
                $stats['this_month']++;
            }
            if (date('Y', strtotime($resignation['created_at'])) === $currentYear) {
                $stats['this_year']++;
            }
        }

        return $stats;
    }

    /**
     * Guardar todas las renuncias en el archivo
     */
    public function saveResignations(array $resignations): bool
    {
        try {
            $content = json_encode($resignations, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
            Storage::disk(self::STORAGE_DISK)->put(self::STORAGE_FILE, $content);
            return true;
        } catch (\Exception $e) {
            Log::error('Error al guardar archivo de renuncias: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Generar ID único para la renuncia
     */
    private function generateUniqueId(): int
    {
        $resignations = $this->getAllResignations();

        if (empty($resignations)) {
            return 1;
        }

        $maxId = max(array_column($resignations, 'id'));
        return $maxId + 1;
    }

    /**
     * Aplicar filtros a las renuncias
     */
    private function applyFilters(array $resignations, array $filters): array
    {
        return array_filter($resignations, function ($resignation) use ($filters) {
            // Filtro por búsqueda (nombre, identificación, email)
            if (!empty($filters['search'])) {
                $search = strtolower($filters['search']);
                $name = strtolower($resignation['employee_name']);
                $identification = strtolower($resignation['employee_identification']);
                $email = strtolower($resignation['employee_email']);

                if (
                    strpos($name, $search) === false &&
                    strpos($identification, $search) === false &&
                    strpos($email, $search) === false
                ) {
                    return false;
                }
            }

            // Filtro por tipo de renuncia
            if (!empty($filters['resignation_type'])) {
                if ($resignation['resignation_type'] !== $filters['resignation_type']) {
                    return false;
                }
            }

            // Filtro por fecha desde
            if (!empty($filters['date_from'])) {
                if (strtotime($resignation['created_at']) < strtotime($filters['date_from'])) {
                    return false;
                }
            }

            // Filtro por fecha hasta
            if (!empty($filters['date_to'])) {
                if (strtotime($resignation['created_at']) > strtotime($filters['date_to'])) {
                    return false;
                }
            }

            return true;
        });
    }
}
