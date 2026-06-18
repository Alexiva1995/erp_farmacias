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
        User::create([
            'username'      => 'admin',
            'email'         => 'admin@golclubsc.com',
            'password_hash' => Hash::make('12345678'),
            'role_id'       => 1, // Rol Admin
            'is_active'     => true,
            'token_login'   => null, // Sin 2FA configurado inicialmente
        ]);
    }
}
