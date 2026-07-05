#!/usr/bin/env php
<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use FerryAI\LlamaBackend\Grammar\GbnfGrammar;
use FerryAI\LlamaBackend\Grammar\JsonSchemaConverter;
use FerryAI\LlamaBackend\Sampling\GreedySampler;
use FerryAI\LlamaBackend\Sampling\TopKSampler;
use FerryAI\LlamaBackend\Sampling\TopPSampler;
use FerryAI\LlamaBackend\Sampling\SamplerFactory;
use FerryAI\Core\ValueObjects\SamplingParams;

echo "=== 09 — Grammar & Samplers ===\n\n";

echo "--- GBNF Grammar ---\n\n";

$yesNo = GbnfGrammar::fromString('root ::= "yes" | "no"');
printf("fromString: %s\n\n", $yesNo->toString());

$jsonSchema = [
    'type' => 'object',
    'properties' => [
        'name' => ['type' => 'string'],
        'age' => ['type' => 'integer'],
        'city' => ['type' => 'string', 'enum' => ['Paris', 'Tokyo', 'NYC']],
    ],
    'required' => ['name', 'age'],
];

$jsonGrammar = GbnfGrammar::fromJsonSchema($jsonSchema);
printf("fromJsonSchema:\n%s\n\n", $jsonGrammar->toString());

echo "--- Samplers ---\n\n";

$logits = array_fill(0, 100, 0.0);
$logits[0] = 10.0;
$logits[1] = 5.0;
$logits[2] = 3.0;

$params = new SamplingParams(temperature: 0.7, topK: 5, topP: 0.9);

$greedy = new GreedySampler();
printf("GreedySampler:  token=%d\n", $greedy->sample($logits, $params));

$topK = new TopKSampler();
printf("TopKSampler:    token=%d\n", $topK->sample($logits, $params));

$topP = new TopPSampler();
printf("TopPSampler:    token=%d\n", $topP->sample($logits, $params));

$factory = new SamplerFactory();
printf("Factory(greedy): token=%d\n", $factory->create('greedy')->sample($logits, $params));
printf("Factory(top-k):  token=%d\n", $factory->create('top-k')->sample($logits, $params));
printf("Factory(top-p):  token=%d\n\n", $factory->create('top-p')->sample($logits, $params));

echo "=== OK ===\n";
