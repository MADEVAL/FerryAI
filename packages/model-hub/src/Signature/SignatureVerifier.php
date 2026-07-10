<?php

declare(strict_types=1);

namespace FerryAI\ModelHub\Signature;

use FerryAI\Core\Exception\InvalidStateException;
use FerryAI\Core\Exception\IoException;
use FerryAI\Core\Exception\ValidationException;

final class SignatureVerifier
{
    public static function verify(string $dataPath, string $signaturePath, string $publicKeyPath): bool
    {
        if (!\extension_loaded('sodium')) {
            return false;
        }

        $data = \file_get_contents($dataPath);
        $signature = \file_get_contents($signaturePath);
        $publicKey = \file_get_contents($publicKeyPath);

        if ($data === false || $signature === false || $publicKey === false) {
            return false;
        }

        if ($data === '' || $signature === '' || $publicKey === '') {
            return false;
        }

        return \sodium_crypto_sign_verify_detached($signature, $data, $publicKey);
    }

    public static function sign(string $dataPath, string $privateKeyPath): string
    {
        if (!\extension_loaded('sodium')) {
            throw new InvalidStateException('ext-sodium is required for signing');
        }

        $data = \file_get_contents($dataPath);
        $privateKey = \file_get_contents($privateKeyPath);

        if ($data === false || $privateKey === false) {
            throw new IoException('Cannot read data or key file');
        }

        if ($data === '' || $privateKey === '') {
            throw new ValidationException('Data or key file is empty');
        }

        return \sodium_crypto_sign_detached($data, $privateKey);
    }
}
