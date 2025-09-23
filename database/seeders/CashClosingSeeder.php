<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use App\Models\User;
use App\Models\CashClosing;
use App\Models\Order;
use Illuminate\Support\Carbon;

class CashClosingSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $sellers = User::get();
        
        if ($sellers->isEmpty()) {
            $seller = User::factory()->create([
                'username' => 'empleado',
                'email' => 'empleado@example.com',
                'password_hash' => Hash::make('12345678'),
                'is_active' => true
            ]);
            $sellers->push($seller);
        }

        foreach ($sellers as $seller) {
            CashClosing::factory()
                ->count(1)
                ->for($seller, 'seller')
                 ->create([
                    'status' => CashClosing::OPEN,
                    'closing_date' => Carbon::now(),
                ]);
        }
    }
}
