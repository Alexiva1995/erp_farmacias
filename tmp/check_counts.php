<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Product;
use Illuminate\Support\Facades\DB;

$total = Product::count();
$is_scarce = Product::where('is_scarce', true)->count();
$not_scarce = Product::where('is_scarce', false)->count();

$ignored = Product::where('is_scarce', false)
    ->where(function ($q) {
        $q->whereNotNull('ignore_until')
            ->where('ignore_until', '>', now());
    })->count();

$assistant_base = Product::where('is_scarce', false)
    ->where(function ($q) {
        $q->whereNull('ignore_until')
            ->orWhere('ignore_until', '<=', now());
    })->count();

echo "Total (not deleted): $total\n";
echo "Redundant (is_scarce=1): $is_scarce\n";
echo "Not Redundant (is_scarce=0): $not_scarce\n";
echo "Ignored (not scarce but ignore_until > now): $ignored\n";
echo "Assistant Base (not scarce and not ignored): $assistant_base\n";

if ($assistant_base + $is_scarce == $total) {
    echo "Sum matches exactly.\n";
} else {
    echo "Sum: " . ($assistant_base + $is_scarce) . " (Diff: " . ($total - ($assistant_base + $is_scarce)) . ")\n";
}
