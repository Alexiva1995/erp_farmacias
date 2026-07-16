<?php

namespace App\Console\Commands;

use App\Models\Product;
use App\Models\Category;
use App\Models\ProductLot;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ImportNotionInventory extends Command
{
    /**
     * El nombre y firma del comando en consola.
     */
    protected $signature = 'app:import-notion';

    /**
     * La descripción del comando.
     */
    protected $description = 'Importa productos e inventario inicial desde el archivo produc.csv de Notion';

    /**
     * Ejecutar el comando.
     */
    public function handle()
    {
        $filePath = base_path('produc.csv');

        if (!file_exists($filePath)) {
            $this->error("❌ El archivo {$filePath} no existe.");
            return 1;
        }

        $this->info("🚀 Iniciando importación desde {$filePath}...");

        $file = fopen($filePath, 'r');
        
        // Leer la primera línea para obtener la cabecera
        $rawHeaders = fgetcsv($file, 0, ',');
        if (!$rawHeaders) {
            $this->error("❌ No se pudo leer la cabecera del archivo.");
            fclose($file);
            return 1;
        }

        // Limpiar cabeceras de posibles espacios, comillas y caracteres especiales ocultos (BOM)
        $headers = array_map(function($header) {
            // Eliminar BOM de UTF-8 y caracteres no imprimibles
            $clean = preg_replace('/[\x00-\x1F\x7F-\xFF]/', '', $header);
            // Limpiar comillas y espacios externos
            return trim(str_replace(['"', "'"], '', $clean));
        }, $rawHeaders);

        $imported = 0;
        $skipped = 0;

        DB::beginTransaction();

        try {
            $rowCount = 0;
            while (($row = fgetcsv($file, 0, ',')) !== false) {
                $rowCount++;
                
                // Si la fila está vacía o no tiene suficientes columnas, la saltamos
                if (count($row) < count($headers) || empty(trim($row[0]))) {
                    $skipped++;
                    continue;
                }

                $rowData = array_combine($headers, array_slice($row, 0, count($headers)));
                
                $productName = trim($rowData['PRODUCTOS'] ?? '');
                $categoryName = trim($rowData['CATEGORÍA'] ?? 'GENERAL');
                $costRaw = trim($rowData['Precio de Compra'] ?? '0');
                $saleRaw = trim($rowData['Precio de Venta'] ?? '0');
                $stockRaw = trim($rowData['Stock Actual'] ?? '0');

                // Si el nombre del producto está vacío, saltar
                if (empty($productName)) {
                    $skipped++;
                    continue;
                }

                // 1. Limpiar precios y stock
                $costPrice = $this->cleanPrice($costRaw);
                $salePrice = $this->cleanPrice($saleRaw);
                $stock = (int)$stockRaw;

                // Si el precio de venta es 0, aplicar margen del 30%
                if ($salePrice <= 0) {
                    $salePrice = round($costPrice * 1.30, 2);
                }

                // 2. Extraer o generar SKU/Código de barras
                $barcode = $this->extractSku($productName, $rowCount);

                // 3. Crear o buscar la categoría
                $category = Category::firstOrCreate([
                    'name' => mb_convert_case($categoryName, MB_CASE_UPPER, "UTF-8")
                ]);

                // 4. Crear o actualizar el producto
                $product = Product::updateOrCreate(
                    ['barcode' => $barcode],
                    [
                        'name' => $productName,
                        'category_id' => $category->id,
                        'cost_price' => $costPrice,
                        'sale_price' => $salePrice,
                        'iva' => false,
                        'psychotropic' => false,
                        'from_colombia' => false,
                    ]
                );

                // 5. Cargar stock si tiene inventario actual
                if ($stock > 0) {
                    ProductLot::create([
                        'product_id' => $product->id,
                        'lot_number' => 'LOTE-INICIAL',
                        'expiration_date' => '2030-12-31', // Lote por defecto
                        'quantity' => $stock,
                        'cost_price' => $costPrice,
                        'location' => 'Tienda',
                    ]);
                }

                $imported++;
            }

            DB::commit();
            fclose($file);

            $this->info("✅ Importación finalizada con éxito.");
            $this->info("📦 Productos importados con lotes iniciales: {$imported}");
            $this->info("⚠️ Omitidos (filas vacías o inválidas): {$skipped}");

            return 0;

        } catch (\Exception $e) {
            DB::rollBack();
            fclose($file);
            $this->error("❌ Error durante la importación: " . $e->getMessage());
            return 1;
        }
    }

    /**
     * Limpia el precio eliminando caracteres de moneda y formateando comas a puntos.
     */
    private function cleanPrice(string $priceString): float
    {
        // Quitar "US$", espacios y caracteres no numéricos excepto coma y punto
        $cleaned = preg_replace('/[^\d,\.]/', '', $priceString);
        
        // Convertir formato con comas (Ej: 4,38) a formato con puntos (4.38)
        $cleaned = str_replace(',', '.', $cleaned);

        // Si hay múltiples puntos por error, dejar solo el último
        if (substr_count($cleaned, '.') > 1) {
            $parts = explode('.', $cleaned);
            $last = array_pop($parts);
            $cleaned = implode('', $parts) . '.' . $last;
        }

        return (float)$cleaned;
    }

    /**
     * Intenta extraer el SKU del nombre del producto, o genera uno correlativo.
     */
    private function extractSku(string $productName, int $index): string
    {
        // Buscar patrón SKU-XXXX o SKUXXXX
        if (preg_match('/(SKU[-_]?\s*[A-Z0-9\-\/]+)/i', $productName, $matches)) {
            $sku = trim($matches[1]);
            // Reemplazar espacios y caracteres extraños
            $sku = str_replace([' ', '/'], ['-', '-'], $sku);
            return strtoupper($sku);
        }

        // Generar un código único correlativo si no tiene SKU explícito
        return 'VD-' . str_pad($index, 4, '0', STR_PAD_LEFT);
    }
}
