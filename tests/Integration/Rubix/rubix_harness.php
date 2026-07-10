<?php

declare(strict_types=1);

/*
 * Isolated RubixML harness — runs in its OWN process, WITHOUT the project's main
 * vendor autoloader, to avoid the amphp/amp files-autoload collision between
 * rubix/ml (amphp ^1) and the dev toolchain (psalm -> amphp ^2/3).
 *
 * Loads the isolated rubix autoloader + a minimal PSR-4 loader for the FerryAI
 * packages under test, then exercises the real CPU backend end to end and prints
 * a single JSON line with the result.
 */

error_reporting(\E_ALL & ~\E_DEPRECATED);

$autoload = getenv('FERRY_AI_RUBIXML_AUTOLOAD') ?: '';

if ($autoload === '' || !is_file($autoload)) {
    echo json_encode(['skip' => 'Set FERRY_AI_RUBIXML_AUTOLOAD to an isolated rubix/ml vendor/autoload.php.']);
    exit(0);
}

require $autoload;

$packages = \dirname(__DIR__, 3) . '/packages';

spl_autoload_register(static function (string $class) use ($packages): void {
    $prefixes = [
        'FerryAI\\CpuBackend\\' => $packages . '/cpu-backend/src/',
        'FerryAI\\Core\\' => $packages . '/core/src/',
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

if (!interface_exists('Rubix\ML\Estimator')) {
    echo json_encode(['skip' => 'rubix/ml not installed']);
    exit(0);
}

$rbmPath = sys_get_temp_dir() . '/ferry-harness-' . uniqid() . '.rbm';

$samples = [[1.0, 1.0], [1.1, 0.9], [5.0, 5.0], [5.1, 4.9], [1.0, 1.2], [5.2, 5.1]];
$labels = ['a', 'a', 'b', 'b', 'a', 'b'];

$estimator = new Rubix\ML\Classifiers\KNearestNeighbors(3);
$estimator->train(new Rubix\ML\Datasets\Labeled($samples, $labels));
(new Rubix\ML\PersistentModel($estimator, new Rubix\ML\Persisters\Filesystem($rbmPath, true, new Rubix\ML\Serializers\RBX())))->save();

$backend = new FerryAI\CpuBackend\CpuNativeBackend();
$model = $backend->load($rbmPath);
$prediction = $model->run(['samples' => [[1.05, 1.0], [5.05, 5.0]]]);

$adapter = new FerryAI\CpuBackend\RubixMLAdapter();
$loaded = $adapter->loadModel($rbmPath);
$proba = $adapter->proba($loaded, [[1.05, 1.0]]);

@unlink($rbmPath);

echo json_encode([
    'available' => $adapter->isAvailable(),
    'output' => $prediction['output'],
    'proba_a' => $proba[0]['a'] ?? null,
]);
