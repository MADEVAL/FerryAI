<?php

declare(strict_types=1);

namespace FerryAI\ModelHub;

use FerryAI\ModelHub\Format\FormatDetector;
use FerryAI\ModelHub\Signature\Sha256Verifier;
use FerryAI\ModelHub\Signature\SignatureVerifier;

final class ModelVerifier
{
    public static function verify(
        string $path,
        ?string $sha256 = null,
        ?string $signature = null,
        ?string $publicKey = null,
    ): bool {
        if ($sha256 !== null) {
            if (!Sha256Verifier::verify($path, $sha256)) {
                return false;
            }
        }

        if ($signature !== null && $publicKey !== null) {
            if (!SignatureVerifier::verify($path, $signature, $publicKey)) {
                return false;
            }
        }

        $format = FormatDetector::detect($path);

        return $format !== 'unknown';
    }

    public static function quickVerify(string $path): bool
    {
        return FormatDetector::detect($path) !== 'unknown';
    }
}
