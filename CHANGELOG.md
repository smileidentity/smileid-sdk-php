# Changelog

All notable changes to this project are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

## [12.0.0] - 2026-08-20

First public release of the Smile ID PHP SDK.

### Added

- Enhanced KYC: verify an ID number against the issuing authority.
- Biometric KYC: verify a selfie against the photo on file with an ID
  authority.
- Document verification and enhanced document verification: verify a selfie
  and liveness images against a photo ID document, with an optional
  authority check.
- Biometric enrollment, authentication, and selfie compare.
- Job status retrieval, with a `waitUntilComplete` helper that polls until a
  job reaches a terminal state.
- Callback replay for a completed verification.
- Sandbox and production environments, with a `baseUrl` override for any
  other host.
- A typed error hierarchy, all extending `SmileIdentity\Errors\SmileIDError`.

[Unreleased]: https://github.com/smileidentity/smileid-sdk-php/compare/v12.0.0...HEAD
[12.0.0]: https://github.com/smileidentity/smileid-sdk-php/releases/tag/v12.0.0
