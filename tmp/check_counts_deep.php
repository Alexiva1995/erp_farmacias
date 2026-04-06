<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Product;

$not_deleted = Product::count(); // Global scope checks is_deleted=0
$redundant = Product::where('is_scarce', true)->count();
$not_redundant = Product::where('is_scarce', false)->count();

$ignored = Product::where('is_scarce', false)
    ->where(function ($q) {
        $q->whereNotNull('ignore_until')
            ->where('ignore_until', '>', now());
    })->count();

$inactive = Product::where('is_scarce', false)
    ->where('is_active', false)->count();

$assistant_base = Product::where('is_scarce', false)
    ->where(function ($q) {
        $q->whereNull('ignore_until')
            ->orWhere('ignore_until', '<=', now());
    })->count();

echo "Total (not deleted): $not_deleted\n";
echo "Redundant (is_scarce=1): $redundant\n";
echo "Not Redundant (is_scarce=0): $not_redundant\n";
echo "Ignored (not scarce, future ignore_until): $ignored\n";
echo "Inactive (not scarce, is_active=0): $inactive\n";
echo "Assistant Base (not scarce, not ignored): $assistant_base\n";

$sum = $assistant_base + $redundant;
echo "Assistant + Redundant = $sum\n";
echo "Missing from Total: " . ($not_deleted - $sum) . "\n";
