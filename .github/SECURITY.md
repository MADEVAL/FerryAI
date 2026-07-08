# Security Policy

## Supported Versions

| Version | Supported |
|---------|-----------|
| 1.x     | ✅ Active |
| < 1.0   | ❌ Pre-release |

## Reporting a Vulnerability

Report security vulnerabilities to the project maintainer.
Do NOT open a public issue.

## Model Verification

FerryAI supports SHA-256 and Ed25519 verification of model files.
Enable verification in config: `'verify_signatures' => true`.

When loading models from HuggingFace Hub, always verify hashes against
known-good values. Do not load models from untrusted sources.
