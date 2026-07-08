# Security Policy

## Supported Versions

| Version | Supported |
|---------|-----------|
| 0.1.x   | ✅ Active (current release line) |

FerryAI is pre-1.0: the latest `0.1.x` release receives security fixes.

## Reporting a Vulnerability

Report security vulnerabilities to the project maintainer.
Do NOT open a public issue.

## Model Verification

FerryAI supports SHA-256 and Ed25519 verification of model files.
Enable verification in config: `'verify_signatures' => true`.

When loading models from HuggingFace Hub, always verify hashes against
known-good values. Do not load models from untrusted sources.
