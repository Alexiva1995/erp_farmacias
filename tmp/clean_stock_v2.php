<?php
$filePath = 'c:/laragon/www/erp_farmacias/app/Repository/ProductRepository.php';
$content = file_get_contents($filePath);

// Reemplazo exacto para la propiedad
$oldProperty = "private \$subConsultaParaCalcularStockPorLotes = '(SELECT COALESCE (SUM(quantity), 0) 
                FROM product_lots 
                WHERE product_id = products.id 
                AND (expiration_date >= CURDATE() OR expiration_date IS NULL))';";

$newProperty = "private \$subConsultaParaCalcularStockPorLotes = '(SELECT COALESCE (SUM(quantity), 0) 
                FROM product_lots 
                WHERE product_id = products.id)';";

$content = str_replace($oldProperty, $newProperty, $content);

// Reemplazo para la línea 36 (o similar)
$oldLine36 = "DB::raw('(SELECT COALESCE(SUM(quantity), 0) FROM product_lots WHERE product_lots.product_id = products.id AND (product_lots.expiration_date >= CURDATE() OR product_lots.expiration_date IS NULL)) as stock'),";
$newLine36 = "DB::raw('(SELECT COALESCE(SUM(quantity), 0) FROM product_lots WHERE product_lots.product_id = products.id) as stock'),";

$content = str_replace($oldLine36, $newLine36, $content);

file_put_contents($filePath, $content);
echo "Expiry filters removed with exact string match.\n";
