<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use App\Models\Employee;
use App\Models\User;
use Carbon\Carbon;

class UsersLegacyImportSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Read SQL file content
        $sqlPath = database_path('seeders/sql/users.sql');
        if (!file_exists($sqlPath)) {
            $this->command->error("Archivo SQL no encontrado: {$sqlPath}");
            return;
        }

        $sqlContent = file_get_contents($sqlPath);

        // Extract column names from INSERT INTO statement
        // Matches: INSERT INTO `users` (`col1`, `col2`, ...) VALUES
        preg_match('/INSERT INTO `users` \((.*?)\) VALUES/s', $sqlContent, $matches);

        if (empty($matches[1])) {
            $this->command->error("No se pudo detectar la estructura de columnas en el INSERT.");
            return;
        }

        // Clean and convert columns to array
        $columns = array_map(function ($col) {
            return trim($col, '` ');
        }, explode(',', $matches[1]));

        $this->command->info("Columnas detectadas: " . implode(', ', $columns));

        // Extract values block
        // Matches content after VALUES until the closing semicolon
        preg_match('/VALUES\s+(.*);/s', $sqlContent, $valuesBlock);

        if (empty($valuesBlock[1])) {
            $this->command->error("No se encontraron valores para insertar.");
            return;
        }

        // Parse individual rows
        // This regex attempts to match grouped values: (v1, v2, ...), (v1, v2, ...)
        // It handles basic quoted string scenarios but might need refinement for complex SQL dumps
        preg_match_all('/\((.*?)\)(?:,|$)/s', $valuesBlock[1], $rows);

        $totalUsers = 0;

        foreach ($rows[1] as $rowString) {
            // Split by comma, respecting quoted strings
            // This is a basic parser. For complex CSV/SQL parsing, a dedicated library is better.
            // Assumption: Standard mysqldump format
            $values = str_getcsv($rowString, ",", "'");

            // Handle "NULL" strings from SQL dump
            $values = array_map(function ($val) {
                return ($val === 'NULL') ? null : $val;
            }, $values);

            if (count($columns) !== count($values)) {
                $this->command->warn("Mismatch de columnas/valores en una fila. Saltando.");
                continue;
            }

            $userData = array_combine($columns, $values);

            // --- EXISTING LOGIC ADAPTED FOR DYNAMIC ARRAY ---

            $userId = $userData['id'];
            $email = $userData['email'];
            $username = explode('@', $email)[0];

            // FIX: Check for duplicate 'admin' username belonging to ANOTHER user
            if ($username === 'admin') {
                $collision = DB::table('users')
                    ->where('username', 'admin')
                    ->where('email', '!=', $email)
                    ->first();

                if ($collision) {
                    DB::table('users')->where('id', $collision->id)->update(['username' => 'adminTest']);
                    $this->command->info("Conflicto detectado: Usuario existente 'admin' (ID: {$collision->id}) renombrado a 'adminTest'.");
                }
            }


            // Determine Role: Legacy is_admin (1) -> Admin (1), (0) -> User (2)
            $roleId = ($userData['is_admin'] == 1) ? 1 : 2;

            // 1. Create or Update User
            $user = User::updateOrCreate(
                ['email' => $email],
                [
                    // 'id' => $userId, // Avoid forcing ID to prevent PRIMARY Key collisions
                    'username' => $username,
                    'role_id' => $roleId,
                    'password_hash' => $userData['password'],
                    'is_active' => $userData['is_active'],
                    'token_login' => $userData['token_login'] ?? null,
                ]
            );

            // 2. Create Employee ONLY if NOT Admin (Role 2)
            if ($roleId === 2) {
                // FIX: Handle NULL identification
                $identification = $userData['cedula'];
                if (empty($identification) || $identification === 'NULL') {
                    $identification = "NV-{$userId}"; // Default value
                    $this->command->warn(" -> Cédula nula para usuario ID {$userId}, asignado: {$identification}");
                }

                Employee::updateOrCreate(
                    ['user_id' => $user->id],
                    [
                        'name' => $userData['first_name'],
                        'last_name' => $userData['last_name'],
                        'identification' => $identification,
                        'photo' => $userData['photo'] ?? null,
                        'is_active' => $userData['is_active'] ?? 1,
                    ]
                );
            }

            $totalUsers++;
        }

        $this->command->info("Procesamiento completado. Total usuarios procesados: {$totalUsers}");
    }
}
