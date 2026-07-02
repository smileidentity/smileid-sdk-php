# Security Policy

## Reporting a vulnerability

If you believe you've found a security vulnerability in this SDK, please report it privately rather than opening a public issue.

**Email:** [security@smileidentity.com](mailto:security@smileidentity.com)

Please include:

- A description of the issue and its potential impact.
- Steps to reproduce, or a proof-of-concept if available.
- The SDK version and PHP version you're using.
- Any relevant code samples (with credentials and partner IDs redacted).
- Your contact details, so we can follow up.

We aim to acknowledge reports within **3 business days** and to provide a substantive response within **10 business days**. Please give us a reasonable opportunity to address the issue before any public disclosure.

## Scope

This repository contains the source code and tests for Smile ID's server-side PHP SDK. Reports relating to:

- Vulnerabilities in the SDK's source code (for example, insecure handling of credentials, signature generation, or file uploads),
- Vulnerable dependencies pulled in via Composer,
- Issues affecting the integrity of this repository (for example, supply-chain concerns in CI workflows),
- Vulnerabilities in the deployed Smile ID API endpoints,

are all in scope and welcome.

## Out of scope

- Vulnerabilities in third-party services we link to (please report those to the relevant vendor).
- Findings that require physical access, social engineering, or DoS testing against production endpoints.
