#!/usr/bin/env php
<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use FerryAI\Core\ValueObjects\Shape;
use FerryAI\CpuBackend\CpuNativeBackend;
use FerryAI\CpuBackend\CpuNativeTensor;
use FerryAI\CpuBackend\RubixMLAdapter;

echo "=== 24 — CPU backend: tensor math + RubixML ===\n\n";

echo "--- CpuNativeTensor arithmetic (pure PHP, no native deps) ---\n\n";

$a = new CpuNativeTensor([1.0, 2.0, 3.0], [3]);
$b = new CpuNativeTensor([4.0, 5.0, 6.0], [3]);

printf("a + b     = %s\n", json_encode($a->add($b)->toArray()));
printf("a - b     = %s\n", json_encode($b->sub($a)->toArray()));
printf("a * b     = %s\n", json_encode($a->mul($b)->toArray()));

$m1 = new CpuNativeTensor([1.0, 2.0, 3.0, 4.0], [2, 2]);
$m2 = new CpuNativeTensor([5.0, 6.0, 7.0, 8.0], [2, 2]);
printf("m1 @ m2   = %s\n", json_encode($m1->matmul($m2)->toArray()));

$mat = new CpuNativeTensor([1.0, 2.0, 3.0, 4.0, 5.0, 6.0], [2, 3]);
printf("transpose = %s\n", json_encode($mat->transpose()->toArray()));
printf("reshape   = %s\n", json_encode((new CpuNativeTensor([1.0, 2.0, 3.0, 4.0], [4]))->reshape(new Shape([2, 2]))->toArray()));
printf("slice     = %s\n\n", json_encode($m1->slice([[0, 1]])->toArray()));

echo "--- CPU backend ---\n\n";

$backend = new CpuNativeBackend();
printf("available: %s\n", $backend->isAvailable() ? 'yes' : 'no');
printf("version:   %s\n\n", $backend->version());

echo "--- RubixML adapter ---\n\n";

$adapter = new RubixMLAdapter();
printf("rubix/ml installed (this process): %s\n\n", $adapter->isAvailable() ? 'yes' : 'no');

if (!$adapter->isAvailable()) {
    echo "RubixML runs in an isolated process (its amphp/amp ^1 collides with the\n";
    echo "dev toolchain's amphp). To exercise real .rbm inference:\n\n";
    echo "  1. composer require rubix/ml   (in a separate directory)\n";
    echo "  2. set FERRY_AI_RUBIXML_AUTOLOAD=<that>/vendor/autoload.php\n";
    echo "  3. php tests/Integration/Rubix/rubix_harness.php\n";
    echo "     -> {\"available\":true,\"output\":[\"a\",\"b\"],\"proba_a\":1}\n\n";
    echo "Once installed, CpuNativeBackend::load('model.rbm') returns a model whose\n";
    echo "run(['samples' => [[...]]]) delegates to the RubixML estimator.\n";
}

echo "\n=== OK ===\n";
