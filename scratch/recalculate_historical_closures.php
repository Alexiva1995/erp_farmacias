<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
$kernel->handle(Illuminate\Http\Request::capture());

use App\Models\DailyCashClosure;
use App\Models\CashClosing;

$ids = [1051, 1052];

foreach ($ids as $id) {
    $daily = DailyCashClosure::find($id);
    if (!$daily) {
        echo "Daily Closure $id not found.\n";
        continue;
    }

    echo "Processing Daily Closure $id...\n";
    $closings = $daily->cashClosings;

    foreach ($closings as $c) {
        echo "  - Recalculating Seller Closure {$c->id}...\n";
        $c->recalculateTotals();
    }

    // Refresh closings after recalculation
    $closings = $daily->cashClosings()->get();

    echo "  - Updating Daily Totals for $id...\n";
    $daily->update([
        'total_sales'   => $closings->sum('total_sales'),
        'total_usd'     => $closings->sum('total_usd'), // No + usd_credit because it's already in total_usd
        'total_cop'     => $closings->sum('total_cop'),
        'total_bs'      => $closings->sum('total_bs'),
        'bs_card'       => $closings->sum('bs_card_debito') + $closings->sum('bs_card_credit'),
        'bs_mobile'     => $closings->sum('bs_mobile'),
        'usd_delivered' => $closings->sum('usd_delivered'),
        'cop_delivered' => $closings->sum('cop_delivered'),
        'bs_delivered'  => $closings->sum('bs_delivered'),
        'total_credits' => $closings->sum('usd_credit'),
    ]);

    echo "Done with $id. New COP: {$daily->total_cop}, New USD: {$daily->total_usd}\n\n";
}
