<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Usuario Administrador
        User::create([
            'first_name' => 'Admin',
            'last_name' => 'Principal',
            'email' => 'admin@example.com',
            'password' => Hash::make('12345678'),
            'is_admin' => true,
            'is_active' => true,
            'token_login' => null
        ]);

        // Usuario "User" (rol estándar)
        User::create([
            'first_name' => 'Usuario',
            'last_name' => 'Estándar',
            'email' => 'user@example.com',
            'password' => Hash::make('12345678'),
            'is_admin' => false,
            'is_active' => true,
            'token_login' => null
        ]);

        // Usuario "Empleado"
        User::create([
            'first_name' => 'Empleado',
            'last_name' => 'Ejemplo',
            'cedula' => '12345678',
            'email' => 'empleado@example.com',
            'password' => Hash::make('12345678'),
            'is_admin' => false,
            'is_active' => true,
            'salary' => 500.00,
            'currency_salary' => 'USD',
            'token_login' => null,
        ]);
    }
}
