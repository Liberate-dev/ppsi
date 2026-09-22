<?php
// Force OPcache clear + confirm file versions
header('Content-Type: text/plain; charset=utf-8');

if (function_exists('opcache_reset')) {
    $result = opcache_reset();
    echo "OPcache reset: " . ($result ? "SUCCESS" : "FAILED") . "\n";
} else {
    echo "OPcache: not enabled\n";
}

echo "\n--- File Timestamps ---\n";
$files = [
    'admin/order_detail.php',
    'admin/dms.php',
    'admin/orders.php',
    'templates/doc_keuangan.php',
    'templates/doc_order.php',
    'templates/doc_member.php',
];
foreach ($files as $f) {
    $path = __DIR__ . '/' . $f;
    if (file_exists($path)) {
        echo "$f: modified " . date('Y-m-d H:i:s', filemtime($path)) . " (" . filesize($path) . " bytes)\n";
    } else {
        echo "$f: FILE NOT FOUND!\n";
    }
}

echo "\n--- Key Strings Check ---\n";
$od = file_get_contents(__DIR__ . '/admin/order_detail.php');
echo "order_detail has 'Tanda Terima 3': " . (str_contains($od, 'Tanda Terima 3') ? 'YES' : 'NO') . "\n";
echo "order_detail has 'tt-panel': " . (str_contains($od, 'tt-panel') ? 'YES' : 'NO') . "\n";
echo "order_detail has 'Walk-In': " . (str_contains($od, 'Walk-In') ? 'YES' : 'NO') . "\n";

$dms = file_get_contents(__DIR__ . '/admin/dms.php');
echo "dms has 'tanda_terima' filter: " . (str_contains($dms, "'tanda_terima'") ? 'YES' : 'NO') . "\n";
echo "dms has 'doc_subtype': " . (str_contains($dms, 'doc_subtype') ? 'YES' : 'NO') . "\n";

$keu = file_get_contents(__DIR__ . '/templates/doc_keuangan.php');
echo "doc_keuangan has 'company-header': " . (str_contains($keu, 'company-header') ? 'YES' : 'NO') . "\n";
echo "doc_keuangan has 'Dicetak oleh': " . (str_contains($keu, 'Dicetak oleh') ? 'YES' : 'NO') . "\n";

echo "\nDone. ";
