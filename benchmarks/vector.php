#!/usr/bin/env php
<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use FerryAI\Profiler;
use FerryAI\Vector\CollectionManager;
use FerryAI\Vector\SQLiteStore;

echo "=== Vector Store Benchmarks ===\n\n";

Profiler::reset();

$store = new SQLiteStore(':memory:');
$manager = new CollectionManager($store);
$collection = $manager->create('bench', 384);

$count = 1000;
$vectors = [];
for ($i = 0; $i < $count; $i++) {
    $vectors[] = array_map(fn(): float => mt_rand() / mt_getrandmax(), range(1, 384));
}

Profiler::start('insert');
foreach ($vectors as $i => $v) {
    $collection->add("vec-$i", $v);
}
Profiler::end('insert');

Profiler::start('search');
for ($i = 0; $i < 100; $i++) {
    $collection->search($vectors[$i % $count], 10);
}
Profiler::end('search');

$report = Profiler::report();

printf("Inserted: %d vectors\n", $count);
printf("Searched: 100 queries (top-10)\n\n");

foreach ($report as $label => $s) {
    printf("%-10s %6.2f ms total  %8.2f ms avg  %6d ops\n", $label, $s['total_ms'], $s['avg_ms'], $s['count']);
}

echo "\nDone.\n";
