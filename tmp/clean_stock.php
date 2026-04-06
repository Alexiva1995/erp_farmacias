<?php
$filePath = 'c:/laragon/www/erp_farmacias/app/Repository/ProductRepository.php';
$content = file_get_contents($filePath);

// 1. Limpiar la propiedad privada centralizada
$content = preg_replace(
    '/private \$subConsultaParaCalcularStockPorLotes = \'(SELECT COALESCE\s*\(SUM\(quantity\), 0\)\s+FROM product_lots\s+WHERE product_id = products\.id\s+)(AND\s+\(expiration_date >= CURDATE\(\) OR expiration_date IS NULL\))\)/s',
    'private $subConsultaParaCalcularStockPorLotes = \'$1)\'',
    $content
);

// 2. Limpiar en builerFiltrarProductforStock (línea 36 aproximadamente)
$content = preg_replace(
    '/(SELECT COALESCE\(SUM\(quantity\), 0\) FROM product_lots WHERE product_lots\.product_id = products\.id\s+)(AND\s+\(product_lots\.expiration_date >= CURDATE\(\) OR product_lots\.expiration_date IS NULL\)\))/s',
    '$1)',
    $content
);

file_put_contents($filePath, $content);
echo "Expiry filters removed from stock calculations.\n";
