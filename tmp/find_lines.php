<?php
$f = file('app/Repository/ProductRepository.php');
foreach($f as $i => $line) {
    if(strpos($line, 'is_colombia') !== false) {
        echo "Line " . ($i + 1) . ": " . trim($line) . "\n";
    }
    if(strpos($line, 'is_ordered') !== false) {
        echo "Line " . ($i + 1) . ": " . trim($line) . "\n";
    }
}
