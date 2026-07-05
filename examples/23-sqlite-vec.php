#!/usr/bin/env php
<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use FerryAI\Vector\Collection;
use FerryAI\Vector\SQLiteStore;
use FerryAI\Vector\SqliteVecExtension;

echo "=== 23 — SQLite + sqlite-vec (vec0) ANN ===\n\n";

if (!class_exists(\Pdo\Sqlite::class)) {
    echo "SKIP: Pdo\\Sqlite is not available (needs PHP 8.4+).\n";
    echo "=== OK ===\n";
    exit(0);
}

$lib = getenv('FERRY_AI_VEC_EXTENSION_LIB') ?: 'D:\\FerryAI\\vec0.dll';

if (!is_file($lib)) {
    echo "SKIP: sqlite-vec library not found: {$lib}\n";
    echo "  Set FERRY_AI_VEC_EXTENSION_LIB to the vec0 shared library to enable native ANN.\n";
    echo "=== OK ===\n";
    exit(0);
}

putenv('FERRY_AI_VEC_EXTENSION_LIB=' . $lib);

$probe = new SqliteVecExtension();

if (!$probe->load(new SQLiteStore(':memory:'))) {
    echo "SKIP: sqlite-vec could not be loaded into the connection.\n";
    echo "=== OK ===\n";
    exit(0);
}

echo "--- Extension ---\n\n";
printf("available: %s\n", $probe->isAvailable() ? 'yes' : 'no');
printf("loaded:    %s\n\n", $probe->isLoaded() ? 'yes' : 'no');

echo "--- Collection with native vec0 index (opt-in via env) ---\n\n";

$store = new SQLiteStore(':memory:');
$store->createCollection('products', 3);
$products = new Collection('products', 3, $store);

$products->add('p1', [0.1, 0.2, 0.3], ['name' => 'Widget', 'category' => 'tools']);
$products->add('p2', [0.4, 0.5, 0.6], ['name' => 'Gadget', 'category' => 'electronics']);
$products->add('p3', [0.11, 0.21, 0.29], ['name' => 'Wrench', 'category' => 'tools']);
$products->add('p4', [0.9, 0.1, 0.0], ['name' => 'Drill', 'category' => 'tools']);

printf("count: %d\n\n", $products->count());

echo "--- Native KNN search (vec0 MATCH ... ORDER BY distance) ---\n\n";
foreach ($products->search([0.1, 0.2, 0.3], k: 3) as $r) {
    printf("  %s  d=%.4f  %s\n", $r['id'], $r['distance'], $r['metadata']['name']);
}

echo "\n--- Filtered search (brute-force fallback keeps filter semantics) ---\n\n";
$filtered = $products->search([0.1, 0.2, 0.3], k: 10, filter: ['category' => ['eq' => 'tools']]);
printf("tools: %d results\n", count($filtered));
foreach ($filtered as $r) {
    printf("  %s  %s\n", $r['id'], $r['metadata']['name']);
}

echo "\n--- Update / delete keep the vec index in sync ---\n\n";
$products->delete('p4');
$products->update('p3', [0.12, 0.22, 0.30]);
printf("after delete+update: %d vectors\n", $products->count());
printf("nearest to p1 now:   %s\n", $products->search([0.1, 0.2, 0.3], 1)[0]['id']);

echo "\n=== OK ===\n";
