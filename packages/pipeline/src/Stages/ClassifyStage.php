<?php

declare(strict_types=1);

namespace FerryAI\Pipeline\Stages;

use FerryAI\Core\Contracts\Backend;
use FerryAI\Core\Contracts\Stage;
use FerryAI\Core\ValueObjects\ClassificationResult;

final class ClassifyStage implements Stage
{
    private ?\FerryAI\Core\Contracts\Model $model = null;

    public function __construct(
        private Backend $backend,
        private string $modelPath,
    ) {}

    #[\Override]
    public function process(mixed $input): mixed
    {
        if ($this->model === null) {
            $this->model = $this->backend->load($this->modelPath);
        }

        $outputs = $this->model->run(['input' => $input]);
        $scores = $outputs['output'] ?? \reset($outputs);

        if (\is_array($scores) && isset($scores[0]) && \is_numeric($scores[0])) {
            $scores = $scores;
        } else {
            return new ClassificationResult('unknown', 0.0);
        }

        $maxIndex = 0;
        $maxScore = $scores[0];

        foreach ($scores as $i => $score) {
            if ($score > $maxScore) {
                $maxScore = $score;
                $maxIndex = $i;
            }
        }

        return new ClassificationResult((string) $maxIndex, (float) $maxScore);
    }

    #[\Override]
    public function name(): string
    {
        return 'classify';
    }
}
