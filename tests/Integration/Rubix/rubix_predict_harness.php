<?php

declare(strict_types=1);

/*
 * Isolated harness: train a RubixML classifier, save it, and predict through AI::predict().
 * Runs in its own process (avoids amphp/amp collision with the dev toolchain).
 *
 * Prints one JSON line: {predict: ["a","b"], proba_a: 1.0} or {error: ...}
 */

$autoload = getenv('FERRY_AI_RUBIXML_AUTOLOAD') ?: '';

if ($autoload === '' || !is_file($autoload)) {
    echo json_encode(['skip' => 'Set FERRY_AI_RUBIXML_AUTOLOAD to an isolated rubix/ml vendor/autoload.php.']);
    exit(0);
}

require $autoload;

if (!interface_exists('Rubix\ML\Estimator')) {
    echo json_encode(['skip' => 'rubix/ml not installed']);
    exit(0);
}

error_reporting(\E_ALL & ~\E_DEPRECATED);

use FerryAI\AI;
use Rubix\ML\Classifiers\KNearestNeighbors;
use Rubix\ML\Datasets\Labeled;

$rbmPath = sys_get_temp_dir() . '/ferry-predict-' . uniqid() . '.rbm';

$samples = [[1.0, 1.0], [1.1, 0.9], [5.0, 5.0], [5.1, 4.9], [1.0, 1.2], [5.2, 5.1]];
$labels = ['a', 'a', 'b', 'b', 'a', 'b'];

$estimator = new KNearestNeighbors(3);
$estimator->train(new Labeled($samples, $labels));
file_put_contents($rbmPath, serialize($estimator));

// Minimal PSR-4 loader for FerryAI CPU packages (vendor/ is not available in this process).
$packagesDir = dirname(__DIR__, 3) . '/packages';

spl_autoload_register(static function (string $class) use ($packagesDir): void {
    $prefixes = [
        'FerryAI\\CpuBackend\\' => $packagesDir . '/cpu-backend/src/',
        'FerryAI\\OnnxBackend\\' => $packagesDir . '/onnx-backend/src/',
        'FerryAI\\LlamaBackend\\' => $packagesDir . '/llama-backend/src/',
        'FerryAI\\Core\\' => $packagesDir . '/core/src/',
        'FerryAI\\' => $packagesDir . '/ai/src/',
    ];

    foreach ($prefixes as $prefix => $dir) {
        if (!str_starts_with($class, $prefix)) {
            continue;
        }

        $relative = str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';

        if (is_file($dir . $relative)) {
            require $dir . $relative;
        }

        return;
    }
});

try {
    AI::config([
        'backend' => 'cpu',
        'backends' => ['predict' => ['model_path' => $rbmPath]],
    ]);

    $predA = AI::predict(['x' => 1.05, 'y' => 1.0]);
    $predB = AI::predict(['x' => 5.05, 'y' => 5.0]);

    @unlink($rbmPath);

    echo json_encode([
        'predict_a' => $predA,
        'predict_b' => $predB,
    ]);
} catch (Throwable $e) {
    @unlink($rbmPath);
    echo json_encode(['error' => $e->getMessage()]);
}
