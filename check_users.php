<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\User;

$u81 = User::find(81);
$u93 = User::find(93);

echo "USER 81: " . json_encode($u81 ? $u81->toArray() : 'NOT FOUND') . "\n";
echo "USER 93: " . json_encode($u93 ? $u93->toArray() : 'NOT FOUND') . "\n";

echo "\nTOTAL USERS COUNT: " . User::count() . "\n";
echo "FIRST 5 USERS:\n";
foreach (User::take(5)->get() as $u) {
    echo "ID: " . $u->id . " | Name: " . $u->name . "\n";
}
