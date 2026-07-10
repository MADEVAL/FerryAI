#!/usr/bin/env php
<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use FerryAI\Vector\CollectionManager;
use FerryAI\Vector\ExportImport;
use FerryAI\Vector\MetadataFilter;
use FerryAI\Vector\SQLiteStore;

echo "=== 10 — Vector Store ===\n\n";

$store = new SQLiteStore(':memory:');
$manager = new CollectionManager($store);

echo "--- CRUD ---\n\n";

$products = $manager->create('products', 3);
$products->add('p1', [0.1, 0.2, 0.3], ['name' => 'Widget', 'price' => 99, 'category' => 'tools']);
$products->add('p2', [0.4, 0.5, 0.6], ['name' => 'Gadget', 'price' => 149, 'category' => 'electronics']);
$products->add('p3', [0.7, 0.8, 0.9], ['name' => 'Screwdriver', 'price' => 12, 'category' => 'tools']);
$products->addBatch([
    ['id' => 'p4', 'vector' => [1.0, 1.0, 1.0], 'metadata' => ['name' => 'Drill', 'price' => 250, 'category' => 'tools']],
    ['id' => 'p5', 'vector' => [0.5, 0.5, 0.5], 'metadata' => ['name' => 'Tablet', 'price' => 499, 'category' => 'electronics']],
]);

printf("count:          %d\n", $products->count());
printf("dimension:      %d\n", $products->dimension());
printf("collectionName: %s\n\n", $products->collectionName());

echo "--- Search ---\n\n";

$query = [0.1, 0.2, 0.3];
$results = $products->search($query, k: 3);

foreach ($results as $r) {
    printf("  %s  d=%.4f  %s ($%d)\n", $r['id'], $r['distance'], $r['metadata']['name'], $r['metadata']['price']);
}

echo "\n--- Search with MetadataFilter ---\n\n";

$results = $products->search($query, k: 10, filter: [
    'and' => [
        ['category' => ['eq' => 'tools']],
        ['price' => ['lt' => 200]],
    ],
]);
printf("tools under $200: %d results\n", count($results));

foreach ($results as $r) {
    printf("  %s  %s ($%d)\n", $r['id'], $r['metadata']['name'], $r['metadata']['price']);
}

echo "\n--- MetadataFilter Operators ---\n\n";

$filter = new MetadataFilter();
$meta = ['price' => 100, 'tags' => ['sale', 'new'], 'name' => 'Test Product'];

printf("price > 50:       %s\n", $filter->matches($meta, ['price' => ['gt' => 50]]) ? 'true' : 'false');
printf("price < 200:      %s\n", $filter->matches($meta, ['price' => ['lt' => 200]]) ? 'true' : 'false');
printf("category exists:  %s\n", $filter->matches($meta, ['category' => ['exists' => true]]) ? 'true' : 'false');
printf("name exists:      %s\n", $filter->matches($meta, ['name' => ['exists' => true]]) ? 'true' : 'false');
printf("name contains 'Test': %s\n\n", $filter->matches($meta, ['name' => ['contains' => 'Test']]) ? 'true' : 'false');

echo "--- Update & Delete ---\n\n";

$products->update('p3', null, ['name' => 'Premium Screwdriver', 'price' => 25]);
$products->delete('p5');
printf("after update+delete: %d vectors\n", $products->count());

$deleted = $products->deleteByFilter(['category' => ['eq' => 'electronics']]);
printf("deletedByFilter(electronics): %d\n\n", $deleted);

echo "--- Export ---\n\n";

$tmpFile = sys_get_temp_dir() . '/ferry-export-' . uniqid() . '.jsonl';
ExportImport::toJson($products, $tmpFile);
printf("exported: %s (%d bytes)\n", basename($tmpFile), filesize($tmpFile));

ExportImport::toCsv($products, sys_get_temp_dir() . '/ferry-export.csv');
printf("CSV exported\n\n");
unlink($tmpFile);

echo "--- Collections ---\n\n";

$manager->create('users', 128);
$manager->create('docs', 768);

printf("collections: %s\n", implode(', ', $manager->list()));
printf("exists('users'): %s\n", $manager->exists('users') ? 'YES' : 'NO');

$manager->delete('users');
printf("after delete users: %s\n\n", implode(', ', $manager->list()));

echo "=== OK ===\n";
