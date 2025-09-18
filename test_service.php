<?php

require_once 'vendor/autoload.php';

$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "=== PROBANDO SERVICIO DE ESTADO DE RESULTADOS ===\n\n";

$service = new \App\Services\FinancialStatementService();

// Probar resumen
echo "1. PROBANDO RESUMEN:\n";
$summary = $service->getIncomeSummary();
echo "Resumen obtenido:\n";
print_r($summary);

echo "\n2. PROBANDO DETALLES:\n";
$details = $service->getIncomeDetails();
echo "Detalles obtenidos:\n";
echo "Ventas: " . count($details['sales']) . "\n";
echo "Gastos: " . count($details['expenses']) . "\n";

echo "\n3. PROBANDO CÁLCULOS INDIVIDUALES:\n";
$startDate = '2020-01-01 00:00:00';
$endDate = '2025-12-31 23:59:59';

$income = $service->calculateTotalIncome($startDate, $endDate);
$costs = $service->calculateTotalCosts($startDate, $endDate);
$expenses = $service->calculateTotalExpenses($startDate, $endDate);
$profit = $service->calculateNetProfit($income, $costs, $expenses);

echo "Ingresos: $income\n";
echo "Costos: $costs\n";
echo "Gastos: $expenses\n";
echo "Utilidad: $profit\n";
