<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\Supplier;
use App\Models\Product;
use App\Models\Laboratory;
use App\Models\Origin;
use App\Models\Category;
use App\Models\ProductLot;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\DailyClosure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class DataImportController extends Controller
{
    public function importCsv(Request $request): JsonResponse
    {
        $request->validate([
            'type' => 'required|string|in:clientes,proveedores,productos,inventariolot,gastos,cierres',
            'file' => 'required|file|mimes:csv,txt',
        ]);

        $type = $request->input('type');
        $file = $request->file('file');
        
        $path = $file->getRealPath();
        $data = array_map(function($line) {
            return str_getcsv($line, ',');
        }, file($path));

        if (count($data) < 2) {
            return response()->json(['message' => 'El archivo está vacío o no contiene filas de datos.'], 422);
        }

        $headers = array_map('trim', $data[0]);
        unset($data[0]); // Remover cabecera

        DB::beginTransaction();
        try {
            $importedCount = 0;
            $errors = [];

            foreach ($data as $index => $row) {
                if (empty($row) || count($row) < count($headers)) {
                    continue;
                }

                $rowData = array_combine($headers, array_slice($row, 0, count($headers)));
                $rowData = array_map('trim', $rowData);

                switch ($type) {
                    case 'clientes':
                        $this->importCliente($rowData);
                        break;
                    case 'proveedores':
                        $this->importProveedor($rowData);
                        break;
                    case 'productos':
                        $this->importProducto($rowData);
                        break;
                    case 'inventariolot':
                        $this->importLote($rowData);
                        break;
                    case 'gastos':
                        $this->importGasto($rowData, $request->user()->id);
                        break;
                    case 'cierres':
                        $this->importCierre($rowData);
                        break;
                }
                $importedCount++;
            }

            DB::commit();
            return response()->json([
                'message' => "Se importaron correctamente {$importedCount} registros de {$type}."
            ], 200);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("Error al importar {$type}: " . $e->getMessage());
            return response()->json([
                'message' => "Error en la línea del archivo: " . $e->getMessage()
            ], 422);
        }
    }

    private function importCliente(array $row): void
    {
        Client::updateOrCreate(
            ['identification' => $row['identification']],
            [
                'identification_type' => $row['identification_type'] ?? 'V-',
                'name' => $row['name'],
                'last_name' => $row['last_name'] ?? null,
                'email' => $row['email'] ?? null,
                'phone' => $row['phone'] ?? null,
                'address' => $row['address'] ?? null,
                'birthdate' => !empty($row['birthdate']) ? $row['birthdate'] : null,
            ]
        );
    }

    private function importProveedor(array $row): void
    {
        Supplier::updateOrCreate(
            ['supplier_name' => $row['supplier_name']],
            [
                'social_reason' => $row['social_reason'] ?? null,
                'sales_phone' => $row['sales_phone'] ?? null,
                'collections_phone' => $row['collections_phone'] ?? null,
                'credit_days' => !empty($row['credit_days']) ? (int)$row['credit_days'] : null,
                'payment_method' => $row['payment_method'] ?? 'Bs',
                'cash_payment' => (bool)($row['cash_payment'] ?? false),
                'charges_igtf' => (bool)($row['charges_igtf'] ?? false),
            ]
        );
    }

    private function importProducto(array $row): void
    {
        $category = null;
        if (!empty($row['category_name'])) {
            $category = Category::firstOrCreate(['name' => $row['category_name']]);
        }

        $laboratory = null;
        if (!empty($row['laboratory_name'])) {
            $laboratory = Laboratory::firstOrCreate(['name' => $row['laboratory_name']]);
        }

        $origin = null;
        if (!empty($row['origin_name'])) {
            $origin = Origin::firstOrCreate(['name' => $row['origin_name']]);
        }

        Product::updateOrCreate(
            ['barcode' => $row['barcode']],
            [
                'name' => $row['name'],
                'active_ingredient' => $row['active_ingredient'] ?? null,
                'category_id' => $category?->id,
                'laboratory_id' => $laboratory?->id,
                'origin_id' => $origin?->id,
                'cost_price' => (float)$row['cost_price'],
                'sale_price' => (float)$row['sale_price'],
                'iva' => (bool)($row['iva'] ?? false),
                'psychotropic' => (bool)($row['psychotropic'] ?? false),
                'from_colombia' => (bool)($row['from_colombia'] ?? false),
            ]
        );
    }

    private function importLote(array $row): void
    {
        $product = Product::where('barcode', $row['barcode'])->first();
        if (!$product) {
            throw new \Exception("Producto con código de barras {$row['barcode']} no existe en el catálogo.");
        }

        $supplier = null;
        if (!empty($row['supplier_name'])) {
            $supplier = Supplier::where('supplier_name', $row['supplier_name'])->first();
        }

        ProductLot::create([
            'product_id' => $product->id,
            'supplier_id' => $supplier?->id,
            'lot_number' => $row['lot_number'] ?? null,
            'expiration_date' => $row['expiration_date'],
            'quantity' => (int)$row['quantity'],
            'cost_price' => !empty($row['cost_price']) ? (float)$row['cost_price'] : $product->cost_price,
            'location' => $row['location'] ?? null,
        ]);
    }

    private function importGasto(array $row, int $userId): void
    {
        $category = ExpenseCategory::firstOrCreate(['name' => $row['category_name']]);

        Expense::create([
            'name' => $row['name'],
            'category_id' => $category->id,
            'amount' => (float)($row['amount'] ?? 0),
            'amount_usd' => (float)($row['amount_usd'] ?? 0),
            'currency' => $row['currency'] ?? 'USD',
            'expense_date' => $row['expense_date'],
            'has_invoice' => (bool)($row['has_invoice'] ?? false),
            'is_deductible' => (bool)($row['is_deductible'] ?? false),
            'user_id' => $userId,
        ]);
    }

    private function importCierre(array $row): void
    {
        DailyClosure::updateOrCreate(
            ['created_at' => $row['date'] . ' 00:00:00'],
            [
                'total_usd' => (float)($row['total_usd'] ?? 0),
                'total_cop' => (float)($row['total_cop'] ?? 0),
                'total_bs' => (float)($row['total_bs'] ?? 0),
                'bs_card' => (float)($row['bs_card'] ?? 0),
                'bs_mobile' => (float)($row['bs_mobile'] ?? 0),
                'usd_delivered' => (float)($row['usd_delivered'] ?? 0),
                'cop_delivered' => (float)($row['cop_delivered'] ?? 0),
                'bs_delivered' => (float)($row['bs_delivered'] ?? 0),
            ]
        );
    }
}
