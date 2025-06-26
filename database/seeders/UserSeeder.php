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
            'username' => 'admin',
            'email' => 'admin@example.com',
            'password_hash' => Hash::make('12345678'),
            'is_active' => true
        ]);

        // Usuario "User" (rol estándar)
        User::create([
            'username' => 'usuario_estandar',
            'email' => 'user@example.com',
            'password_hash' => Hash::make('12345678'),
            'is_active' => true,
        ]);

        // Usuario "Empleado"
        User::create([
            'username' => 'empleado',
            'email' => 'empleado@example.com',
            'password_hash' => Hash::make('12345678'),
            'is_active' => true
        ]);
    }
}
