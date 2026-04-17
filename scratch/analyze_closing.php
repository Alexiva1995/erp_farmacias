<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\CashClosing;
use App\Models\Order;

$id = 6589;
$closing = CashClosing::find($id);

if (!$closing) {
    echo "Closing not found\n";
    exit;
}

echo "CashClosing ID: " . $closing->id . "\n";

$orders = Order::where('cash_closing_id', $id)->where('status', 'Completed')->get();

$totalOrderAmountCop = 0;
$totalCashCopPaymentBruto = 0;
$totalReturnsInCop = 0;

echo "--- Orders Analysis ---\n";
foreach ($orders as $order) {
    echo "Order #{$order->id} (Currency: {$order->currency}, Total: {$order->total_amount}):\n";
    
    $orderCashCopBruto = 0;
    foreach ($order->payment_methods as $pm) {
        $method = $pm['method'] ?? '';
        $amount = (float)($pm['amount'] ?? 0);
        
        if ($method === 'cash_cop' || ($method === 'Cash' && ($pm['currency'] ?? '') === 'COP')) {
            $orderCashCopBruto += $amount;
        }
    }
    
    echo "   - Cash COP Payment (Bruto): $orderCashCopBruto\n";
    echo "   - Returns (Neto descontado): {$order->money_returns}\n";
    echo "   - Neto en Caja: " . ($orderCashCopBruto - (float)$order->money_returns) . "\n";
    
    if ($order->currency === 'COP') {
        $totalOrderAmountCop += $order->total_amount;
    }
    $totalCashCopPaymentBruto += $orderCashCopBruto;
    $totalReturnsInCop += (float)($order->money_returns ?? 0);
}

echo "\n--- Totals for Completed ---\n";
echo "Sum of 'total_amount' (Net Vendor Sales): " . $totalOrderAmountCop . "\n";
echo "Sum of Gross 'cash_cop' payments: " . $totalCashCopPaymentBruto . "\n";
echo "Sum of 'money_returns': " . $totalReturnsInCop . "\n";
echo "Total Net Cash Expected (Bruto - Returns): " . ($totalCashCopPaymentBruto - $totalReturnsInCop) . "\n";

echo "\n--- Closing DB Values ---\n";
echo "cop_cash (from DB - reported as Gross): " . $closing->cop_cash . "\n";
echo "total_cop (from DB - reported as Sales): " . $closing->total_cop . "\n";
echo "cop_delivered (from DB - what to deliver): " . $closing->cop_delivered . "\n";

$diffNeto = $totalOrderAmountCop - $closing->total_cop;
echo "\nDiscrepancy (Actual Sales - Reported Sales): " . $diffNeto . "\n";
