<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use App\Models\Employee;
use Carbon\Carbon;

class UsersLegacyImportSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $users = [
            [
                'id' => 1,
                'first_name' => 'Administrador',
                'last_name' => 'SOPORTE',
                'email' => 'admin@farmaciabs.com',
                'email_verified_at' => '2022-09-03 21:00:04',
                'password' => '$2y$10$tAheLN4ShIRxKeSAh9bc7.6Y9N..YI5NINXQSjNw5p8P8JvxTx2SW', // password
                'is_admin' => 1,
                'created_at' => '2022-09-03 21:00:04',
                'updated_at' => '2025-07-24 19:45:20',
                'cedula' => '0',
                'photo' => null,
                'is_active' => 1,
                'token_login' => 'XTC3HYQSLQJN2SGL'
            ],
            [
                'id' => 2,
                'first_name' => 'User',
                'last_name' => 'SOPORTE',
                'email' => 'user@farmaciabs.com',
                'email_verified_at' => '2022-09-03 21:00:04',
                'password' => '$2y$10$3HuMDaO1OUfYeZEQjNthKeVKeP3G/5uhyL3adAJbuYlBMKzFVge1u',
                'is_admin' => 0,
                'created_at' => '2022-09-03 21:00:04',
                'updated_at' => '2025-07-24 14:49:07',
                'cedula' => null,
                'photo' => null,
                'is_active' => 1,
                'token_login' => 'MHQA2FGCVE6GST4J'
            ],
            [
                'id' => 59,
                'first_name' => 'Alexis',
                'last_name' => 'Valera',
                'email' => 'alexis@farmaciabs.com',
                'email_verified_at' => '2023-08-15 19:34:24',
                'password' => '$2y$10$7TNsU8WqWxTbpkczuCmU0eZaUTMMsmM8eclMwkG3QFIxQ7o1ON7hC',
                'is_admin' => 0,
                'created_at' => '2023-08-15 19:34:24',
                'updated_at' => '2025-08-05 13:59:35',
                'cedula' => '241509805',
                'photo' => null,
                'is_active' => 1,
                'token_login' => 'YY7QAWUJIWBB4MZ4'
            ],
            [
                'id' => 70,
                'first_name' => 'yenireth',
                'last_name' => 'itanare',
                'email' => 'yenirethitanare@farmaciabs.com',
                'email_verified_at' => '2024-10-18 23:30:51',
                'password' => '$2y$10$BZvQmfWJTAGCxjYKEBxvAOA/U1sZKzJwAqucoDXYNFAGMGqccQV6O',
                'is_admin' => 0,
                'created_at' => '2024-10-18 23:30:51',
                'updated_at' => '2025-07-29 19:24:44',
                'cedula' => '30335463',
                'photo' => null,
                'is_active' => 1,
                'token_login' => 'CM5AOA7LLMRQSO5A'
            ],
            [
                'id' => 77,
                'first_name' => 'Maria',
                'last_name' => 'Martinez',
                'email' => 'mariamartinez@farmaciabs.com',
                'email_verified_at' => '2025-03-03 15:45:39',
                'password' => '$2y$10$3b3LYRM2EsNzUnxjPnGewO6G5U1RxIIq1/PtDvJkDu10Q8H7KyuXW',
                'is_admin' => 0,
                'created_at' => '2025-03-03 15:45:39',
                'updated_at' => '2025-05-30 16:24:01',
                'cedula' => '32130078',
                'photo' => null,
                'is_active' => 1,
                'token_login' => '2ONKZULYAVQYG6D5'
            ],
            [
                'id' => 81,
                'first_name' => 'Jackeline',
                'last_name' => 'Varela',
                'email' => 'jackelinvarela@farmaciabs.com',
                'email_verified_at' => '2025-04-30 13:46:40',
                'password' => '$2y$10$tAheLN4ShIRxKeSAh9bc7.6Y9N..YI5NINXQSjNw5p8P8JvxTx2SW',
                'is_admin' => 0,
                'created_at' => '2025-04-30 13:46:40',
                'updated_at' => '2025-11-04 22:13:45',
                'cedula' => '34917767',
                'photo' => null,
                'is_active' => 1,
                'token_login' => '6EYJFIABGOFZD4S3'
            ],
            [
                'id' => 82,
                'first_name' => 'Paola',
                'last_name' => 'Barreto',
                'email' => 'paolabarreto@farmaciabs.com',
                'email_verified_at' => '2025-05-14 21:46:06',
                'password' => '$2y$10$Ko8XI0hl933WtBCmoolhweRUiyz.rHNyfHICUuPvBXhyWv.i72Hyq',
                'is_admin' => 0,
                'created_at' => '2025-05-14 21:46:06',
                'updated_at' => '2025-11-04 20:47:40',
                'cedula' => '28017946',
                'photo' => null,
                'is_active' => 1,
                'token_login' => '2BZB2AMWWRHPA2IK'
            ],
            [
                'id' => 84,
                'first_name' => 'Estefanny',
                'last_name' => 'Torrado',
                'email' => 'estefannytorrado@farmaciabs.com',
                'email_verified_at' => '2025-06-11 04:27:18',
                'password' => '$2y$10$5Aj/mb13TRgtCHP.h.nzK.VHaE/ByC1T6xOGKFzjUQixe8nz1QWXO',
                'is_admin' => 1, // Nota: En el dump dice 1, pero si no quieres empleado para este user, se tratará como Admin.
                'created_at' => '2025-06-11 04:27:18',
                'updated_at' => '2025-06-11 04:29:45',
                'cedula' => '27108387',
                'photo' => null,
                'is_active' => 1,
                'token_login' => '7PF2UGCXQYJNW3VF'
            ],
            [
                'id' => 87,
                'first_name' => 'ORIANA ANELIX',
                'last_name' => 'BARBOZA COLMENARES',
                'email' => 'orianabarboza@farmaciabs.com',
                'email_verified_at' => '2025-08-24 16:18:11',
                'password' => '$2y$10$N0xLJH38B4YUcyyLxB4dROuE7xPjzJeO9Nb64UGjfa1gQ13O6cu/C',
                'is_admin' => 0,
                'created_at' => '2025-08-24 16:18:11',
                'updated_at' => '2025-11-25 14:22:29',
                'cedula' => '32394926',
                'photo' => null,
                'is_active' => 1,
                'token_login' => 'RYKPVH2NMMPUEKS6'
            ],
            [
                'id' => 88,
                'first_name' => 'Mayela',
                'last_name' => 'Morales',
                'email' => 'mayelamorales@farmaciabs.com',
                'email_verified_at' => '2025-09-02 19:53:11',
                'password' => '$2y$10$cj31kHRaqpOWj5rwO9WQaeKxR.f2BYg1ggOfKxKI4tPUfaC8PGaoS',
                'is_admin' => 0,
                'created_at' => '2025-09-02 19:53:11',
                'updated_at' => '2025-09-02 19:55:42',
                'cedula' => '9351893',
                'photo' => null,
                'is_active' => 1,
                'token_login' => 'TZGKTF4YJE2NU6JU'
            ]
        ];

        foreach ($users as $userData) {

            // 1. Crear Usuario
            // Extraer username del email (antes del @)
            $username = explode('@', $userData['email'])[0];

            // FIX: Check for duplicate 'admin' username belonging to ANOTHER user
            if ($username === 'admin') {
                $collision = DB::table('users')
                    ->where('username', 'admin')
                    ->where('email', '!=', $userData['email'])
                    ->first();

                if ($collision) {
                    // Rename the conflicting user
                    DB::table('users')->where('id', $collision->id)->update(['username' => 'adminTest']);
                    $this->command->info("Conflicto detectado: Usuario existente 'admin' (ID: {$collision->id}) renombrado a 'adminTest'.");
                }
            }

            $roleId = $userData['is_admin'] == 1 ? 1 : 2;

            $userExists = DB::table('users')->where('email', $userData['email'])->first();

            $userId = null;

            if ($userExists) {
                // Actualizar
                DB::table('users')->where('id', $userExists->id)->update([
                    'username' => $username,
                    'role_id' => $roleId,
                    'password_hash' => $userData['password'],
                    'is_active' => $userData['is_active'],
                    'token_login' => $userData['token_login'],
                    // 'email_verified_at' removed
                    'created_at' => $userData['created_at'],
                    'updated_at' => $userData['updated_at'],
                ]);
                $userId = $userExists->id;
                $this->command->info("Actualizado usuario: {$userData['email']}");
            } else {
                // Insertar
                $idExists = DB::table('users')->where('id', $userData['id'])->exists();

                $insertData = [
                    'username' => $username,
                    'role_id' => $roleId,
                    'email' => $userData['email'],
                    'password_hash' => $userData['password'],
                    'is_active' => $userData['is_active'],
                    'token_login' => $userData['token_login'],
                    // 'email_verified_at' removed
                    'created_at' => $userData['created_at'],
                    'updated_at' => $userData['updated_at'],
                ];

                if (!$idExists) {
                    $insertData['id'] = $userData['id'];
                }

                $userId = DB::table('users')->insertGetId($insertData);
                $this->command->info("Creado usuario: {$userData['email']} (ID: $userId)");
            }

            // 2. Crear Empleado (Solo si NO es Admin)
            if ($userData['is_admin'] == 0) {
                $employee = Employee::where('user_id', $userId)->first();

                if (!$employee) {
                    // FIX: Handle NULL identification
                    $identification = $userData['cedula'];
                    if (empty($identification)) {
                        $identification = "NV-{$userId}"; // Default value
                        $this->command->warn(" -> Cédula nula para usuario ID {$userId}, asignado: {$identification}");
                    }

                    Employee::create([
                        'user_id' => $userId,
                        'name' => $userData['first_name'],
                        'last_name' => $userData['last_name'],
                        'identification' => $identification,
                        'is_active' => $userData['is_active'],
                        'photo' => $userData['photo'],
                        'rif' => null,
                        'residence_letter' => null,
                        'cv' => null,
                    ]);
                    $this->command->info(" -> Empleado creado para: {$userData['first_name']} {$userData['last_name']}");
                } else {
                    $this->command->info(" -> El empleado ya existe para este usuario.");
                }
            } else {
                $this->command->info(" -> Usuario Admin, omitiendo creación de empleado.");
            }
        }
    }
}
