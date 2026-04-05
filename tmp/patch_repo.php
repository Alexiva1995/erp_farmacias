<?php
$filePath = 'c:/laragon/www/erp_farmacias/app/Repository/ProductRepository.php';
$content = file_get_contents($filePath);

// 1. Añadir where('is_scarce', false) en el constructor de consulta Average (IA Assistant)
$content = preg_replace(
    '/(builerFiltrarProductForIaOrderAssistantTypeAverage.*?where\(\'is_deleted\', false\))/s',
    '$1->where(\'is_scarce\', false)',
    $content,
    1
);

// 2. Ordenamiento alfabético en Average (IA Assistant)
$oldOrder = 'if (array_key_exists("sortBy", $filtros) && array_key_exists("orderBy", $filtros)) {
            $consulta->orderBy($filtros["sortBy"], $filtros["orderBy"]);
        } else {
            $consulta->orderBy("solicitar", "DESC");
        }';

$newOrder = 'if (array_key_exists("sortBy", $filtros) && array_key_exists("orderBy", $filtros)) {
            $consulta->orderBy($filtros["sortBy"], $filtros["orderBy"]);
        } else {
            if (array_key_exists("tipo_vista", $filtros) && $filtros["tipo_vista"] == true) {
                $consulta->join(\'groups_products\', \'groups_products.id\', \'=\', \'products.group_id\')
                    ->orderBy(\'groups_products.name\', \'ASC\')
                    ->orderBy(\'solicitar\', \'DESC\');
            } else {
                $consulta->orderBy("solicitar", "DESC");
            }
        }';

// Reemplazar la primera ocurrencia (Average)
$content = str_replace($oldOrder, $newOrder, $content);

// 3. Filtros is_scarce en Sales (IA Assistant)
// total_group_sales subquery en Sales (buscamos la que NO tiene is_scarce todavía)
$content = preg_replace(
    '/(builerFiltrarProductForIaOrderAssistantTypeSales.*?WHERE p\.group_id = products\.group_id\s+AND o\.status = "Completed"\s+)(AND o\.created_at)/s',
    '$1AND p.is_scarce = 0 $2',
    $content
);

// preferencia_product denominator en Sales
$content = preg_replace(
    '/(builerFiltrarProductForIaOrderAssistantTypeSales.*?preferencia_product.*?WHERE p\.group_id = products\.group_id\s+AND o\.status = "Completed"\s+)(AND o\.created_at)/s',
    '$1AND p.is_scarce = 0 $2',
    $content
);

// where('is_scarce', false) en el constructor de consulta Sales (IA Assistant)
$content = preg_replace(
    '/(builerFiltrarProductForIaOrderAssistantTypeSales.*?where\(\'is_deleted\', false\))/s',
    '$1->where(\'is_scarce\', false)',
    $content,
    1
);

// 4. Reportes Individuales (Average)
// total_group_sales en Individual Average
$content = preg_replace(
    '/(builerFiltrarIndividualProductForAssistantReportTypeAverage.*?WHERE p\.group_id = products\.group_id\s+AND o\.status = "Completed"\s+)(AND o\.created_at)/s',
    '$1AND p.is_scarce = 0 $2',
    $content
);

// sales_average sum en Individual Average
$content = preg_replace(
    '/(builerFiltrarIndividualProductForAssistantReportTypeAverage.*?)SUM\(sales_average\) OVER \(PARTITION BY group_id\)/s',
    '$1SUM(CASE WHEN is_scarce = 0 THEN sales_average ELSE 0 END) OVER (PARTITION BY group_id)',
    $content
);

// where('is_scarce', false) en Individual Average
$content = preg_replace(
    '/(builerFiltrarIndividualProductForAssistantReportTypeAverage.*?where\(\'is_deleted\', false\))/s',
    '$1->where(\'is_scarce\', false)',
    $content,
    1
);

// 5. Reportes Individuales (Sales)
// total_group_sales en Individual Sales
$content = preg_replace(
    '/(builerFiltrarIndividualProductForAssistantReportTypeSales.*?WHERE p\.group_id = products\.group_id\s+AND o\.status = "Completed"\s+)(AND o\.created_at)/s',
    '$1AND p.is_scarce = 0 $2',
    $content
);

// preferencia_product denominator en Individual Sales
$content = preg_replace(
    '/(builerFiltrarIndividualProductForAssistantReportTypeSales.*?preferencia_product.*?WHERE p\.group_id = products\.group_id\s+AND o\.status = "Completed"\s+)(AND o\.created_at)/s',
    '$1AND p.is_scarce = 0 $2',
    $content
);

// where('is_scarce', false) en Individual Sales
$content = preg_replace(
    '/(builerFiltrarIndividualProductForAssistantReportTypeSales.*?where\(\'is_deleted\', false\))/s',
    '$1->where(\'is_scarce\', false)',
    $content,
    1
);

file_put_contents($filePath, $content);
echo "Patch applied successfully.\n";
