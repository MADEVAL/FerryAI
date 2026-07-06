<?php

declare(strict_types=1);

namespace FerryAI\Tests\Verification;

use FerryAI\Core\Enums\DType;
use FerryAI\Core\Logger;
use FerryAI\Core\ValueObjects\Shape;
use FerryAI\ModelHub\Format\FormatDetector;
use FerryAI\Tensor\ArrayTensor;
use FerryAI\Vector\Collection;
use FerryAI\Vector\ExportImport;
use FerryAI\Vector\SQLiteStore;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;

/**
 * Runtime regression guards for the round-2 audit fixes. Each test asserts the CORRECTED
 * behaviour so a regression is caught by execution, not source reading.
 */
#[CoversNothing]
final class AuditRound2Test extends TestCase
{
    public function testArrayTensorTransposeRejectsDuplicateAxes(): void
    {
        $t = ArrayTensor::fromNested([[1, 2], [3, 4]]);

        $this->expectException(\FerryAI\Core\Exception\ValidationException::class);
        $t->transpose([0, 0]);
    }

    public function testArrayTensorTransposeRejectsOutOfRangeAxis(): void
    {
        $t = ArrayTensor::fromNested([[1, 2], [3, 4]]);

        $this->expectException(\Throwable::class);
        $t->transpose([0, 2]);
    }

    public function testExportImportCsvProperlyEscapesSpecialCharacters(): void
    {
        $store = new SQLiteStore(':memory:');
        $store->createCollection('c', 2);
        $collection = new Collection('c', 2, $store);
        $collection->add('id,with"quote', [1.0, 2.0], ['title' => 'a,b "q"']);

        $csv = tempnam(sys_get_temp_dir(), 'ferry_csv_');
        self::assertIsString($csv);
        ExportImport::toCsv($collection, $csv);

        $lines = array_values(array_filter(explode("\n", (string) file_get_contents($csv))));
        $fields = str_getcsv($lines[1], ',', '"', '\\');

        self::assertCount(3, $fields);
        self::assertSame('id,with"quote', $fields[0]);

        @unlink($csv);
    }

    public function testExportImportFromJsonSkipsNonArrayVector(): void
    {
        $store = new SQLiteStore(':memory:');
        $path = tempnam(sys_get_temp_dir(), 'ferry_vec_');
        self::assertIsString($path);
        file_put_contents(
            $path,
            json_encode(['id' => 'ok', 'vector' => [1.0, 2.0]]) . "\n"
            . json_encode(['id' => 'bad', 'vector' => 'not-array']) . "\n",
        );

        $imported = ExportImport::fromJson($path, 'imp', 2, $store);

        self::assertSame(1, $imported->count());

        @unlink($path);
    }

    public function testFormatDetectorIdentifiesSafetensorsCorrectlyEvenWithOnnxLikeBytes(): void
    {
        $file = tempnam(sys_get_temp_dir(), 'ferry_st_');
        self::assertIsString($file);
        // header length 8 -> first byte 0x08 (was misread as ONNX)
        file_put_contents($file, pack('P', 8) . '{"a":"b"}' . str_repeat("\x00", 32));

        self::assertSame('safetensors', FormatDetector::detect($file));

        @unlink($file);
    }

    public function testLoggerTreatsUppercaseLevelCorrectly(): void
    {
        $file = tempnam(sys_get_temp_dir(), 'ferry_log_');
        self::assertIsString($file);

        $logger = new Logger($file, 'WARNING');
        $logger->debug('suppressed');

        self::assertStringNotContainsString('suppressed', (string) file_get_contents($file));

        @unlink($file);
    }
}
