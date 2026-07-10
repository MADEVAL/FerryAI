# Security

## Model provenance & verification

Models are executable artefacts -- treat them like dependencies.

- **Never commit models** to the repo (they are `.gitignore`d).
- Verify downloads before use:
  - **SHA-256** (`FerryAI\ModelHub\Signature\Sha256Verifier`) against a known digest.
  - **Ed25519** signatures (`SignatureVerifier`, requires `ext-sodium`).
  - `verify_signatures` config gates enforcement in the hub.
- Only load models from sources you trust (official HuggingFace repos, your own artefacts).

## Deserialization (RubixML `.rbm`)

The CPU backend loads RubixML estimators. RubixML's RBX format is preferred; a legacy path uses
PHP `unserialize()`. **Only load `.rbm` files you produced/trust** -- `unserialize` on untrusted
input is dangerous. See the CPU backend notes.

## FFI boundary

Native inference uses PHP FFI to load shared libraries (ONNX Runtime, `ferry_llama.dll`,
sqlite-vec, tokenizers-cpp). Implications:

- Load native libraries only from controlled paths; the directory on `PATH` should not be
  world-writable.
- FFI executes native code in-process -- a malicious/corrupt library has full process access.
- FerryAI never uses `shell_exec`/subprocess to Python; all native access is FFI.

## Input handling

- SQL identifiers for vector collections are validated (`PostgresStore::vectorTableName` rejects
  injection); values are always bound via PDO.
- HuggingFace API calls use HTTPS; set an API token via the client for private repos and keep it
  out of source control (use env vars).

## Grammar-constrained output

For untrusted downstream consumers, constrain LLM output with a GBNF `grammar` (see
[backends/llama](backends/llama.md)) so responses conform to an expected shape.

## Secrets

Keep API tokens, DSNs and paths in environment variables (`FERRY_AI_*`), not in code. See
[configuration](configuration.md).
