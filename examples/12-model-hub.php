#!/usr/bin/env php
<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use FerryAI\ModelHub\Format\AiArchive;
use FerryAI\ModelHub\Format\FormatDetector;
use FerryAI\ModelHub\HuggingFaceClient;
use FerryAI\ModelHub\ModelIntrospector;
use FerryAI\ModelHub\Signature\Sha256Verifier;
use FerryAI\ModelHub\Signature\SignatureVerifier;

$modelDir = getenv('FERRY_AI_MODEL_DIR') ?: 'D:\FerryAI\all-MiniLM-L6-v2-onnx';
$modelPath = $modelDir . '/model.onnx';
$tokenizerPath = $modelDir . '/tokenizer.json';

echo "=== 12 — Model Hub ===\n\n";

if (file_exists($modelPath)) {
    echo "--- Format Detection ---\n\n";

    printf("detect(model.onnx):        %s\n", FormatDetector::detect($modelPath));
    printf("detect(tokenizer.json):     %s\n\n", FormatDetector::detect($tokenizerPath));
}

echo "--- Model Introspection ---\n\n";

if (file_exists($modelPath)) {
    $meta = ModelIntrospector::introspect($modelPath);
    printf("name:       %s\n", $meta->name);
    printf("sizeBytes:  %d\n", $meta->sizeBytes);
    printf("format:     %s\n\n", FormatDetector::detect($modelPath));
}

echo "--- SHA-256 Verification ---\n\n";

if (file_exists($modelPath)) {
    $hash = Sha256Verifier::compute($modelPath);
    printf("sha256:         %s\n", $hash);
    printf("verify(correct): %s\n", Sha256Verifier::verify($modelPath, $hash) ? 'PASS' : 'FAIL');
    printf("verify(wrong):   %s\n\n", Sha256Verifier::verify($modelPath, str_repeat('0', 64)) ? 'PASS' : 'FAIL');
}

echo "--- Ed25519 Signature ---\n\n";

if (extension_loaded('sodium')) {
    $keypair = sodium_crypto_sign_keypair();
    $publicKey = sodium_crypto_sign_publickey($keypair);
    $privateKey = sodium_crypto_sign_secretkey($keypair);

    $pubPath = sys_get_temp_dir() . '/ferry-pub.key';
    $privPath = sys_get_temp_dir() . '/ferry-priv.key';

    file_put_contents($pubPath, $publicKey);
    file_put_contents($privPath, $privateKey);

    if (file_exists($modelPath)) {
        $signature = SignatureVerifier::sign($modelPath, $privPath);
        $sigPath = sys_get_temp_dir() . '/ferry.sig';
        file_put_contents($sigPath, $signature);

        printf(
            "verifyEd25519(correct): %s\n",
            SignatureVerifier::verify($modelPath, $sigPath, $pubPath) ? 'PASS' : 'FAIL',
        );
    }

    unlink($pubPath);
    unlink($privPath);

    if (isset($sigPath)) {
        unlink($sigPath);
    }
    echo "\n";
} else {
    echo "SKIP: ext-sodium not loaded\n\n";
}

echo "--- AiArchive ---\n\n";

$archivePath = sys_get_temp_dir() . '/ferry-test.ai';
AiArchive::create($archivePath, [
    'test.txt' => $modelDir . '/tokenizer_config.json',
]);

printf("create:     %s\n", basename($archivePath));
printf("validate:   %s\n", AiArchive::validate($archivePath) ? 'PASS' : 'FAIL');
printf("files:      %s\n", implode(', ', AiArchive::list($archivePath)));

unlink($archivePath);
echo "\n";

echo "--- HuggingFace Hub ---\n\n";

$hf = new HuggingFaceClient();
$info = $hf->getModelInfo('sentence-transformers/all-MiniLM-L6-v2');

if ($info !== []) {
    printf("Model:      %s\n", $info['modelId'] ?? $info['id'] ?? '?');
    printf("Pipeline:   %s\n", $info['pipeline_tag'] ?? '?');
    printf("Downloads:  %s\n\n", number_format($info['downloads'] ?? 0));
}

echo "--- Hub Cache ---\n\n";

\FerryAI\AI::config(['backend' => 'onnx']);
$hub = \FerryAI\AI::hub();
printf("cacheSize:  %d bytes\n", $hub->cacheSize());

$cached = $hub->cached('all-MiniLM-L6-v2');
printf("cached:     %s\n\n", $cached ?? 'not cached');

echo "=== OK ===\n";
