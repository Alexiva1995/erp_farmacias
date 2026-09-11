<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $dronenaStructure = [
            '0' => [
                'type' => 'string',
                'target' => 'cod_supplier',
                'file_field' => 'A'
            ],
            '1' => [
                'type' => 'string',
                'target' => 'name',
                'file_field' => 'B'
            ],
            '2' => [
                'type' => 'decimal',
                'target' => 'unit_cost',
                'file_field' => 'C'
            ],
            '3' => [
                'type' => 'decimal',
                'target' => 'quantity',
                'file_field' => 'D'
            ],
            '6' => [
                'type' => 'decimal',
                'target' => 'discount_percentage',
                'file_field' => 'G'
            ],
            '9' => [
                'type' => 'string',
                'target' => 'barcode_match',
                'file_field' => 'J'
            ],
            '13' => [
                'type' => 'date',
                'target' => 'expiration',
                'file_field' => 'N'
            ]
        ];

        $supplierIds = DB::table('suppliers')
            ->where('name', 'LIKE', '%NENA%')
            ->pluck('id')
            ->toArray();

        $supplierIds = array_unique(array_merge($supplierIds, [27, 1014]));

        DB::table('supplier_connections')
            ->where(function ($query) use ($supplierIds) {
                $query->whereIn('supplier_id', $supplierIds)
                      ->orWhere('host', 'LIKE', '%dronena%');
            })
            ->update([
                'structure' => json_encode($dronenaStructure)
            ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
    }
};