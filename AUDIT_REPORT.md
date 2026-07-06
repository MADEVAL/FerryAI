### 20. `OnnxTensor::__unserialize` uses `DType::from()` and `Device::from()` without try-catch

**File:** `packages/onnx-backend/src/OnnxTensor.php:182-183`

```php
$this->dtype = DType::from((string) $data['dtype']);
$this->deviceType = Device::from((string) $data['device']);
```

If corrupted serialized data is passed, `ValueError` from the enum `::from()` propagates as an uncaught exception during `unserialize()`, which may produce confusing error messages.

---

### 21. `PostgresCollection` stores `$dimension` but never validates vector dimensions on `add()`

**File:** `packages/vector/src/PostgresCollection.php`

The contract `VectorStore::add()` says «throws ShapeMismatchException when the dimension does not match the collection». `Collection` (SQLite) validates dimensions; `PostgresCollection` delegates to PostgreSQL and lets it fail at the database level (which produces a PDOException, not a `ShapeMismatchException`).

---

### 22. `ChatFormatter::detectFormat()` regex for "phi" uses fragile pattern

**File:** `packages/llama-backend/src/ChatFormatter.php:44`

```php
\preg_match('/(?<![a-z])phi/', $name) === 1 => 'phi',
```

The negative lookbehind `(?<![a-z])` prevents matching "dolphin" (verified by test), but also misses variations like "my-phi-3" where the match is at position 0. The test case at `AuditFindingsTest.php:99-102` confirms the fix works for "dolphin" and "microsoft/phi-3". **Not a bug** — the pattern is correct. Retracted.

---

### 23. `hub::downloadWithProgress()` and `Downloader::downloadWithProgress()` — both stubs

Both the `Hub` (facade layer) and `Downloader` (transport layer) have stub implementations returning `['progress' => 0, 'downloaded' => 0, 'total' => 0]`. Two independent stubs for the same feature.

---

### 24. No `DataFrame::fromCsv` test with actual CSV file

All `DataFrame` tests use `fromArray`. The `fromCsv` path goes through `CsvReader` which is tested separately. This is fine as an architectural choice but means there's no integration test of `DataFrame::fromCsv` end-to-end.

---

### 25. `LlamaBackend::load()` ignores `$device` for model params

**File:** `packages/llama-backend/src/LlamaBackend.php:52-57`

```php
$target = Device::resolve($device ?? Device::AUTO, $this->availableDevices());
$gpuLayers = $target === Device::CPU ? 0 : 999;

$session = $this->runtime->createSession(
    $source,
    new LlamaModelParams(nGpuLayers: $gpuLayers),
    new LlamaContextParams(nGpuLayers: $gpuLayers),
);
```

Both `LlamaModelParams` and `LlamaContextParams` receive `nGpuLayers: $gpuLayers`. But `LlamaModelParams` doesn't have an `nGpuLayers` constructor parameter — it has `$nGpuLayers`. Wait, let me re-check: `LlamaModelParams` has `public int $nGpuLayers = 0`. Yes it does. And `LlamaContextParams` has `public int $nGpuLayers = 0`. So this is correct. Retracted.

---

## LOW Issues

### 26. `StreamResponse` SSE output: tokens containing newlines break SSE

If a generated token contains `\n`, the SSE line becomes multi-line (SSE uses `\n` as frame separator). This can cause clients to misinterpret the event boundary. Fix: escape newlines in token strings or use `data: ` prefix per-line.

---

### 27. `ReturnTypeWillChange` not needed but `#[\Override]` used on interface methods

The `Tensor` contract uses `#[\Override]` on `jsonSerialize()` — this is valid in PHP 8.3+ (the project requires PHP 8.5). No issue. Retracted.

---

### 28. `phpstan.neon` — `typeAliases` defined but never used

```neon
typeAliases:
    TokenIds: list<int>
    Vector: list<float>
```

These type aliases are defined but not referenced anywhere in the codebase (no `@phpstan-type` usage). Dead configuration.

---

### 29. Missing `ext-pdo_sqlite` in root `composer.json` `require`

The root `composer.json` requires `ext-pdo` (via the vector package) but `ext-pdo_sqlite` is only `suggest`-ed. If tests use SQLite-backed vector stores (which they do — `AuditFindingsTest`, `AuditRound2Test`), the extension must be present. This is fine for `suggest` since the vector store supports PostgreSQL too. Not a bug.

---

### 30. `.editorconfig` defines `insert_final_newline = true` but `.env` files may lack it

Some files in the project (`.env` files in `tests/`) end without a newline. The `.editorconfig` should enforce this. Minor formatting inconsistency.

---

### 31. `PHP8.5` usage of `private const array` syntax — pre-release dependency

Multiple files use `private const array AUTO_DETECT_ORDER = [...]`. This syntax (typed class constants with `array`) requires PHP 8.3+. Since the project targets PHP 8.5, this is fine. But if backporting to PHP 8.2 is ever desired, this would need changing.

---

### 32. `composer.json` lacks `"mutation"` script for Infection

`infection.json5` is configured with `minMsi: 70`, but there's no `composer mutation` script in the root `composer.json` (AGENTS.md documents it as existing). The command doesn't exist in the scripts section, so `composer mutation` would fail.

---

### 33. Redundant `??=` operators

In `AI.php:350-351`:
```php
private static function observability(): Observability
{
    return self::$observability ??= new Observability();
}
```
But `AI::config()` already sets `self::$observability = Observability::fromConfig(self::$config)`. The `??=` fallback only triggers if `config()` was never called, which would cause `ensureConfigured()` to throw earlier. The second `Observability` constructor (with all flags `false`) is unreachable.

---

## Verification Tests Status

The verification test suite (`tests/Verification/`) has 16 passing regression tests (10 in `AuditFindingsTest`, 6 in `AuditRound2Test`). These guard against previously-found issues:

| Test | Issue Guarded |
|------|---------------|
| `testBpeDecodeStripsFusedEndOfWordMarker` | BPE `</w>` marker appearing in decoded output |
| `testSeededTopPSamplerAdvancesRngAcrossTokens` | Deterministic RNG causing degenerate single-token output |
| `testGbnfMatcherMatchesAnyCharAtom` | GBNF `.` (any-char) not matching |
| `testJsonSchemaConverterMakesPropertiesOptionalWhenRequiredAbsent` | JSON Schema `required` absent → wrong required properties |
| `testSha256VerifierAcceptsStandardChecksumFileWithFilename` | SHA256 verifier rejecting standard checksum format |
| `testChatFormatterDoesNotMisdetectDolphinAsPhi` | "dolphin" model name matching phi regex |
| `testEosPoolingReturnsEmptyOnEmptyHiddenStates` | EOS pooling crash on empty input |
| `testCollectionHonoursConfiguredEuclideanMetric` | Vector store ignoring configured distance metric |
| `testSqliteStoreRejectsUnsafeCollectionName` | SQL injection via collection name in DDL |
| `testAiConfigHonoursConfiguredBackend` | Backend config not propagated |
| `testArrayTensorTransposeRejectsDuplicateAxes` | Invalid transpose axes silently accepted |
| `testArrayTensorTransposeRejectsOutOfRangeAxis` | Out-of-range axis in transpose |
| `testExportImportCsvProperlyEscapesSpecialCharacters` | CSV export with embedded quotes/commas |
| `testExportImportFromJsonSkipsNonArrayVector` | JSON import crash on non-array vector field |
| `testFormatDetectorIdentifiesSafetensorsCorrectlyEvenWithOnnxLikeBytes` | Safetensors misidentified as ONNX |
| `testLoggerTreatsUppercaseLevelCorrectly` | Uppercase log level not recognised |

All verification tests reference previously fixed bugs — confirming the audit process was already followed for these issues.

---

## Architecture Conformance

| Rule (AGENTS.md) | Status |
|------------------|--------|
| FFI is the only bridge to native code | PASS |
| Backend isolation (backends never know about each other) | PASS |
| Contracts define truth | PASS (with exceptions noted above) |
| Exceptions extend `FerryAIException` with `errorCode()` | PASS |
| Zero-copy | PARTIAL — `OnnxModel::run()` calls `toArray()` on Tensor input |
| No hard framework coupling | PASS (Laravel/Symfony packages are thin wrappers) |
| Inference-only, no training | PASS |

---

## Test Coverage Assessment

- **Unit tests:** 125+ test files across all 14 packages
- **Integration tests:** 16 files under `tests/Integration/` (require native libraries)
- **Verification tests:** 2 files (16 tests) guarding previously-found bugs
- **Contract tests:** `ContractsTest.php` validates all 10 interface contracts exist with correct method signatures

**Coverage gaps:**
- `Hub::warmup()`, `Hub::downloadWithProgress()`, `Hub::checkUpdates()` — no tests (they're stubs)
- `Downloader::download()` — no direct unit test
- `OnnxInspector` and `GgufInspector` — tested only at the unit level with `ModelIntrospector`; their stub methods aren't caught because the test mocks the file existence
- No mutation testing CI job defined (infection.json5 present but no script)

---

## Recommendations

1. **IMMEDIATE:** Fix the 6 CRITICAL issues before any release — especially `Hub::verify()` (silent security bypass) and `Hub::warmup()` (broken contract).
2. **NEXT:** Address HIGH issues — `ModelPool::release()` no-op, Laravel/Symfony service provider classes, missing default config for `classify`/`moderate`/`predict`.
3. **PLAN:** Extract a shared `AbstractTensor` to eliminate the ~60% code duplication between `CpuNativeTensor` and `ArrayTensor`.
4. **TOOLING:** Narrow Psalm's `Mixed*` suppressions to FFI-boundary files only, to catch real mixed-type bugs in pure PHP code.
5. **TOOLING:** Add `"mutation": "@php vendor/bin/infection"` to root `composer.json` scripts.
6. **TESTING:** Add contract conformance tests that verify implementations (not just interfaces) satisfy the contract — e.g., that `Hub::warmup()` actually downloads uncached models.
