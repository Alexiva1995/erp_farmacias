<?php

namespace Database\Seeders;

use App\Models\Location;
use Illuminate\Database\Seeder;

class LocationSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $locations = [
            "E-001", "E-002", "E-003", "E-004", "E-005", "E-006", "E-007", "E-008", "E-009", "E-010",
            "G-001", "G-002", "G-003", "G-004", "G-005", "G-006", "G-007", "G-008", "G-009", "G-010",
            "I-001", "I-002", "I-003", "I-004", "I-005", "I-006", "I-007", "I-008", "I-009", "I-010",
            "N-001", "N-002",
            "P-001", "P-002", "P-003", "P-004", "P-005", "P-006", "P-007", "P-008", "P-009", "P-010",
            "D-001", "D-002", "D-003", "D-004", "D-005", "D-006", "D-007", "D-008", "D-009", "D-010",
        ];

        foreach ($locations as $location) {
            Location::firstOrCreate(['name' => $location]);
        }
    }
}
