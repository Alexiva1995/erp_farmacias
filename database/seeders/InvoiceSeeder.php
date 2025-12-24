<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\DB;

class InvoiceSeeder extends Seeder
{
    public function run(): void
    {
        try {
            DB::beginTransaction();

            $headersData = $this->loadJsonRows('data/invoices.json');
            $linesData = $this->loadJsonRows('data/invoice_details.json');

            if (empty($headersData) && empty($linesData)) {
                DB::rollBack();
                return;
            }

            $validClientIds = DB::table('clients')->pluck('id')->flip();
            $validSellerIds = DB::table('users')->pluck('id')->flip();
            $validProductIds = DB::table('products')->pluck('id')->flip();

            $idMap = [];
            $orderDetailsBatch = [];
            $fiscalDetailsBatch = [];

            // Facturas
            foreach ($headersData as $header) {
                $invoiceDate = $this->parseDate($header['Fecha'] ?? null);
                $createdAt = $this->parseDateTime($header['created_at'] ?? now());

                $clientId = $header['cod_cliente'] ?? null;
                $sellerId = $header['id_vendedor'] ?? null;

                if ($clientId !== null && !isset($validClientIds[$clientId]))
                    $clientId = null;
                if ($sellerId !== null && !isset($validSellerIds[$sellerId]))
                    $sellerId = null;

                $orderId = DB::table('orders')->insertGetId([
                    'client_id' => $clientId,
                    'seller_id' => $sellerId,
                    'cash_closing_id' => 1,
                    'total_amount' => $header['total'] ?? 0,
                    'money_returns' => 0,
                    'usd_conversion' => 0,
                    'currency' => 'Bs',
                    'total_cost' => 0,
                    'order_date' => $invoiceDate,
                    'status' => 'completed',
                    'has_multiple_currencies' => 0,
                    'payment_methods' => null,
                    'total_amount_usd' => 0,
                    'url_recipe' => null,
                    'created_at' => $createdAt,
                    'updated_at' => $createdAt,
                ]);

                $fiscalHistoryId = DB::table('fiscal_history')->insertGetId([
                    'user_id' => $sellerId,
                    'fiscal_id' => $header['Num_Fac_Fiscal'] ?? null,
                    'invoice_number' => null,
                    'business_name' => $header['nombre'] ?? 'N/A',
                    'identification' => $header['rif'] ?? 'N/A',
                    'address' => $header['direccion'] ?? 'N/A',
                    'exempt_amount' => 0.0,
                    'iva_amount' => $header['iva'] ?? 0,
                    'spe' => 0,
                    'total_amount' => $header['total'] ?? 0,
                    'taxable_amount' => null,
                    'invoice_date' => $invoiceDate,
                    'order_id' => $orderId,
                    'status' => 'finalized',
                    'created_at' => $createdAt,
                    'updated_at' => $createdAt,
                ]);

                $facturaId = $header['factura_id'] ?? null;
                if (!empty($facturaId)) {
                    $idMap[$facturaId] = [
                        'order_id' => $orderId,
                        'fiscal_history_id' => $fiscalHistoryId,
                        'created_at' => $createdAt,
                    ];
                }
            }

            // Detalles de Facturas
            foreach ($linesData as $line) {
                $fid = $line['factura_id'] ?? null;
                if (!$fid)
                    continue;

                // Registros sin relación
                if (!isset($idMap[$fid])) {
                    $createdAt = $this->parseDateTime($line['created_at'] ?? now());
                    $invoiceDate = substr($createdAt, 0, 10);

                    $oId = DB::table('orders')->insertGetId([
                        'cash_closing_id' => 1,
                        'total_amount' => 0,
                        'currency' => 'Bs',
                        'money_returns' => 0,
                        'usd_conversion' => 0,
                        'total_cost' => 0,
                        'order_date' => $invoiceDate,
                        'status' => 'completed',
                        'created_at' => $createdAt,
                        'updated_at' => $createdAt,
                    ]);

                    $fHId = DB::table('fiscal_history')->insertGetId([
                        'order_id' => $oId,
                        'business_name' => 'N/A',
                        'identification' => 'N/A',
                        'address' => 'N/A',
                        'iva_amount' => 0,
                        'total_amount' => 0,
                        'taxable_amount' => 0,
                        'invoice_date' => $invoiceDate,
                        'status' => 'finalized',
                        'created_at' => $createdAt,
                        'updated_at' => $createdAt,
                    ]);

                    $idMap[$fid] = ['order_id' => $oId, 'fiscal_history_id' => $fHId, 'created_at' => $createdAt];
                }

                $map = $idMap[$fid];
                $productId = $line['cod_producto'] ?? null;
                if ($productId !== null && !isset($validProductIds[$productId]))
                    $productId = null;

                $orderDetailsBatch[] = [
                    'order_id' => $map['order_id'],
                    'product_id' => $productId,
                    'product_type' => 'normal',
                    'quantity' => $line['cantidad'] ?? 0,
                    'price' => ($line['precio'] ?? 0) * ($line['cantidad'] ?? 0),
                    'unit_cost' => $line['precio'] ?? 0,
                    'created_at' => $line['created_at'] ?? $map['created_at'],
                    'updated_at' => $line['updated_at'] ?? $map['created_at'],
                ];

                $fiscalDetailsBatch[] = [
                    'fiscal_history_id' => $map['fiscal_history_id'],
                    'product_id' => $productId,
                    'product_name' => $line['nombre'] ?? 'N/A',
                    'quantity' => $line['cantidad'] ?? 0,
                    'vat_status' => $line['act_iva'] ?? 0,
                    'big_amount' => $line['big'] ?? 0,
                    'total_amount' => ($line['cantidad'] ?? 0) * ($line['precio'] ?? 0),
                    'created_at' => $line['created_at'] ?? $map['created_at'],
                    'updated_at' => $line['updated_at'] ?? $map['created_at'],
                ];
            }

            foreach (array_chunk($orderDetailsBatch, 1000) as $chunk) {
                if (!DB::table('order_details')->insert($chunk)) {
                    throw new \Exception("Batch insert failed for order_details");
                }
            }
            foreach (array_chunk($fiscalDetailsBatch, 1000) as $chunk) {
                if (!DB::table('fiscal_history_details')->insert($chunk)) {
                    throw new \Exception("Batch insert failed for fiscal_history_details");
                }
            }

            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            $this->command->error('FATAL ERROR: ' . $e->getMessage());
            throw $e;
        }
    }

    protected function loadJsonRows(string $path): array
    {
        $fullPath = database_path($path);
        if (!File::exists($fullPath))
            throw new \Exception("File MISSING: $fullPath");
        $json = File::get($fullPath);
        $data = json_decode($json, true);
        if (json_last_error() !== JSON_ERROR_NONE)
            throw new \Exception("Invalid JSON in $path");
        return $data['rows'] ?? $data ?? [];
    }

    protected function parseDate(?string $date): ?string
    {
        if (!$date)
            return null;
        return preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) ? $date : null;
    }

    protected function parseDateTime(?string $datetime): string
    {
        if (!$datetime)
            return now()->toDateTimeString();
        return preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $datetime) ? $datetime : now()->toDateTimeString();
    }
}
