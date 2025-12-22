<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\File;
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

        $json = File::get(database_path('data/cash_closing.json'));
        $data = json_decode($json, true);
        $cashClosingData = [];

        foreach ($data as $row) {
            $cashClosingData[] = [
                "status" => 'closed',
                "seller_id" => 1,
                "total_usd" => $row['amount_usd'] ?? 0,
                "total_bs" => $row['amount_bs'] ?? 0,
                "total_cop" => $row['amount_cop'] ?? 0,
                "total_sales" => $row['total_amount'] ?? 0,
                'usd_delivered' => $row['entrega_usd'] ?? 0,
                'cop_delivered' => $row['entrega_cop'] ?? 0,
                'closing_date' => $row['end_date'] ?? now(),
                'created_at' => $row['created_at'],
                'updated_at' => $row['updated_at'],
            ];
        }

        CashClosing::insert($cashClosingData);
    }
}
