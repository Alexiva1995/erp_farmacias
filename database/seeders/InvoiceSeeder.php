<?php

namespace Database\Seeders;

use App\Models\Supplier;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\DB;

class InvoiceSeeder extends Seeder
{
    public function run(): void
    {
        try {
            DB::beginTransaction();

            $json = File::get(database_path('data/invoices.json'));
            $invoices = json_decode($json, true);
            $json = File::get(database_path('data/invoice_details.json'));
            $details = json_decode($json, true);
            $data = [];

            $suppliers = Supplier::query()->pluck('id')->flip();

            foreach ($invoices as $invoice) {
                $data[] = [
                    "id" => $invoice['id'],
                    "invoice_number" => $invoice['number'],
                    "supplier_id" => $suppliers->has($invoice['supplier_id']) ? $invoice['supplier_id'] : null,
                    "status_payment" => $invoice['was_paid'],
                    "exp_date" => $this->parseDate($invoice['expiration_date']),
                    "created_at" => $this->parseDateTime($invoice['created_at']),
                    "updated_at" => $this->parseDateTime($invoice['updated_at']),
                    "exchange_rate" => $invoice['tasa_bcv'],
                    "created_invoice_date" => $this->parseDate($invoice['date_emission']),
                    "received_date" => $invoice['fecha_recibo'],
                    "exempt_amount" => $invoice['monto_excento_iva'] ?? 0.0,
                    "total_amount" => $invoice['total'] ?? 0.0,
                    "total_usd" => $invoice['total_usd'] ?? 0.0,
                    "control_number" => $invoice['num_control'],
                    "uploaded_by" => 1,
                    "registered_by" => 1,
                ];
            }

            foreach (array_chunk($data, 1000) as $chunk) {
                if (!DB::table('invoices')->insert($chunk)) {
                    throw new \Exception("Batch insert failed for invoices");
                }
            }

            unset($data);
            $invoices = DB::table('invoices')->pluck('id')->flip();
            $products = DB::table('products')->pluck('id')->flip();

            foreach ($details as $detail) {
                $data[] = [
                    "id" => $detail['id'],
                    "invoice_id" => $invoices->has($detail['invoice_id']) ? $detail['invoice_id'] : null,
                    "product_id" => $products->has($detail['product_id']) ? $detail['product_id'] : null,
                    "lot_number" => $detail['p_lot'] ?? null,
                    "created_at" => $this->parseDateTime($detail['created_at']),
                    "updated_at" => $this->parseDateTime($detail['updated_at']),
                    "expiration_date" => !array_key_exists('expiration_date', $detail) ? null : $this->parseDate($detail['expiration_date']),
                    "quantity" => $detail['quantity'],
                    "unit_cost" => !array_key_exists('p_cost', $detail) ? 0 : $detail['p_cost'],
                    "total_cost" => !array_key_exists('p_cost', $detail) ? 0 : $detail['quantity'] * $detail['p_cost'],
                    "location" => null,
                    "tax_enabled" => $detail["p_has_tax"] ?? false,
                ];
            }

            foreach (array_chunk($data, 1000) as $chunk) {
                if (!DB::table('invoice_details')->insert($chunk)) {
                    throw new \Exception("Batch insert failed for invoice details");
                }
            }

            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            $this->command->error('FATAL ERROR: ' . $e->getMessage());
            throw $e;
        }
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
