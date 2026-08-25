<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\SupplierConnection;

class UpdateCristmedicalsConfig extends Command
{
    protected $signature = 'supplier:update-cristmedicals';
    protected $description = 'Actualiza la configuracion de la API para Cristmedicals (Supplier ID 3)';

    public function handle()
    {
        $connection = SupplierConnection::whereHas('supplier', function ($q) {
            $q->where('name', 'LIKE', '%CRIST%');
        })->orWhere('host', 'LIKE', '%cristmedicals%')
          ->orWhere('supplier_id', 1002)
          ->orWhere('supplier_id', 3)
          ->first();

        if (!$connection) {
            $this->error('No se encontro la conexion para Cristmedicals.');
            return;
        }

        $connection->type = 'api';
        $connection->has_header = true;
        $connection->host = '';
        $connection->invoice_path = '/';
        $connection->password = null;
        // El codigo de cliente de farmacia (co_cli) para Cristmedicals
        $connection->username = 'FAR00818';

        $connection->structure = [
            [ "target" => "name", "file_field" => "des_art", "type" => "string" ],
            [ "target" => "barcode_match", "file_field" => "codigo_barra", "type" => "string" ],
            [ "target" => "unit_cost_usd", "file_field" => "precio_con_descuento", "type" => "decimal" ],
            [ "target" => "cod_supplier", "file_field" => "co_art", "type" => "string" ]
        ];

        $connection->invoice_structure = [
            "mode" => "grouped",
            "header" => [
                [ "field" => "invoice_number", "original_field" => "fact_num", "type" => "string" ],
                [ "field" => "date", "original_field" => "fec_emis", "type" => "date", "format" => "Y-m-d" ],
                [ "field" => "total_amount", "original_field" => "sub_total", "type" => "decimal" ],
                [ "field" => "tax_amount", "original_field" => "iva16", "type" => "decimal" ],
                [ "field" => "exempt_amount", "original_field" => "exento", "type" => "decimal" ],
                [ "field" => "exchange_rate", "original_field" => "tasa", "type" => "decimal" ]
            ],
            "lines" => [
                [ "field" => "barcode", "original_field" => "sku", "type" => "string" ],
                [ "field" => "name", "original_field" => "descrip", "type" => "string" ],
                [ "field" => "quantity", "original_field" => "cant", "type" => "integer" ],
                [ "field" => "unit_cost", "original_field" => "neto_und", "type" => "decimal" ],
                [ "field" => "total_cost", "original_field" => "total", "type" => "decimal" ]
            ]
        ];

        $connection->save();

        $this->info('Configuracion actualizada exitosamente para Cristmedicals (ID 13).');
    }
}
