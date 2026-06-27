<?php
namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Usuario Administrador Único
        User::updateOrCreate(
            ['username' => 'admin'],
            [
                'email'         => 'admin@golclubsc.com',
                'password_hash' => Hash::make('12345678'),
                'role_id'       => 1, // Rol Admin
                'is_active'     => true,
                'token_login'   => null,
            ]
        );

        // Usuario Cliente/Tienda de Compras E-commerce
        User::updateOrCreate(
            ['username' => 'tienda'],
            [
                'email'         => 'tienda@tova.com',
                'password_hash' => Hash::make('tienda123'),
                'role_id'       => 2, // Rol Cliente / Tienda
                'is_active'     => true,
                'token_login'   => null,
            ]
        );
    }
}
