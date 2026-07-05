#!/usr/bin/env php
<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use FerryAI\Vector\PostgresCollection;
use FerryAI\Vector\PostgresStore;
use FerryAI\Vector\PostgresVecIndex;

echo "=== 21 — PostgreSQL + pgvector Vector Store ===\n\n";

if (!extension_loaded('pdo_pgsql')) {
    echo "SKIP: ext-pdo_pgsql is not installed.\n";
    echo "=== OK ===\n";
    exit(0);
}

$dsn = getenv('FERRY_AI_PG_DSN') ?: 'pgsql:host=127.0.0.1;port=5432';
$user = getenv('FERRY_AI_PG_USER') ?: 'postgres';
$pass = getenv('FERRY_AI_PG_PASSWORD') ?: 'postgres';

try {
    $store = new PostgresStore($dsn, $user, $pass);
} catch (Throwable $e) {
    echo 'SKIP: PostgreSQL/pgvector unavailable: ' . $e->getMessage() . "\n";
    echo "  Set FERRY_AI_PG_DSN / FERRY_AI_PG_USER / FERRY_AI_PG_PASSWORD to point at a server\n";
    echo "  with the pgvector extension available (CREATE EXTENSION vector).\n";
    echo "=== OK ===\n";
    exit(0);
}

$collection = 'example_products';
$store->dropCollection($collection);
$store->createCollection($collection, 3, 'cosine');

$products = new PostgresCollection($collection, 3, $store, 'cosine');

echo "--- CRUD ---\n\n";

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

echo "--- Native ANN search (pgvector <=> operator) ---\n\n";

$query = [0.1, 0.2, 0.3];
foreach ($products->search($query, k: 3) as $r) {
    printf("  %s  d=%.4f  %s ($%d)\n", $r['id'], $r['distance'], $r['metadata']['name'], $r['metadata']['price']);
}

echo "\n--- Search with metadata filter ---\n\n";

$results = $products->search($query, k: 10, filter: [
    'and' => [
        ['category' => ['eq' => 'tools']],
        ['price' => ['lt' => 200]],
    ],
]);
printf("tools under \$200: %d results\n", count($results));
foreach ($results as $r) {
    printf("  %s  %s ($%d)\n", $r['id'], $r['metadata']['name'], $r['metadata']['price']);
}

echo "\n--- HNSW index (approximate nearest neighbour) ---\n\n";

$index = new PostgresVecIndex($store);
$index->createIndex($collection, 'hnsw', 'cosine');
printf("HNSW index created; top match: %s\n", $products->search($query, k: 1)[0]['id']);

echo "\n--- Update & delete ---\n\n";

$products->update('p3', null, ['name' => 'Premium Screwdriver', 'price' => 25]);
$products->delete('p5');
printf("after update+delete: %d vectors\n", $products->count());

$deleted = $products->deleteByFilter(['category' => ['eq' => 'electronics']]);
printf("deletedByFilter(electronics): %d\n\n", $deleted);

echo "--- Collections ---\n\n";

printf("collections: %s\n", implode(', ', $store->listCollections()));

$store->dropCollection($collection);
printf("after drop: %s\n\n", implode(', ', $store->listCollections()) ?: '(none)');

echo "=== OK ===\n";
