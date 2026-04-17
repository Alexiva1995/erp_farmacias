<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\CashClosing;
use Illuminate\Support\Facades\DB;

DB::beginTransaction();

try {
    // 1. Create temporary closing
    $closing = CashClosing::create([
        'seller_id' => 1,
        'status' => 'open',
        'closing_date' => now(),
    ]);

    echo "Initial State:\n";
    echo "total_cop: {$closing->total_cop}, cop_delivered: {$closing->cop_delivered}\n\n";

    // 2. Simulate Order 1: Sales 12,000 COP, Payment 20,000 COP, Change 8,000 COP
    // In OrderActionService, cop_cash is updated with Net (20k - 8k = 12k)
    $closing->cop_cash += 12000;
    $closing->recalculateTotals();

    echo "After Order 1 (COP Sales 12k, Net Cash 12k):\n";
    echo "total_cop: {$closing->total_cop} (Expected 12000)\n";
    echo "cop_delivered: {$closing->cop_delivered} (Expected 12000)\n\n";

    // 3. Simulate Order 2: Sales 5 USD, Payment 10 USD, Change 19,000 COP
    // In OrderActionService:
    // usd_cash += 10, usd_cash -= 5 (change usd). Net = 5.
    // cop_conversion += 19000.
    $closing->usd_cash += 5;
    $closing->cop_conversion += 19000;
    $closing->recalculateTotals();

    echo "After Order 2 (USD Sales 5, Change 19k COP):\n";
    echo "total_cop: {$closing->total_cop} (Expected 12000 - No change because Sales are COP only)\n";
    echo "cop_delivered: {$closing->cop_delivered} (Expected 12000 - 19000 = -7000)\n\n";

    if ($closing->total_cop == 12000 && $closing->cop_delivered == -7000) {
        echo "VERIFICATION SUCCESSFUL: Sales COP is not affected by USD-order change, while Physical Cash correctly accounts for it.\n";
    } else {
        echo "VERIFICATION FAILED.\n";
    }

} finally {
    DB::rollBack();
}
