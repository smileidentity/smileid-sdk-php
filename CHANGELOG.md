# Changelog

All notable changes to this project are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

## [13.0.0] - 2026-11-09

This is a major version because it changes the public API of 12.1.0. Version
12.2.0 was never published; its changes ship in this release.

### Breaking changes

- `biometricKyc->verify` now takes `consent`, `country`, `idType`, `idNumber`
  and `userDetails` first, and the images are optional. Call it with named
  arguments.
- `biometric->authenticate` no longer takes `useEnrolledImage`, and
  `selfieImage` and `livenessImages` are required. Pass both images, or use
  `biometricKyc->verify(useEnrolledImage: true, userId: ...)` instead.
- `SmileIdentity\Client\Auth\TokenManager` and `Transport::tokenManager()` are
  removed. The client now mints a token for each request; remove any code that
  used them.
- A 401 response now raises `AuthenticationError` straight away instead of
  retrying with a new token. Check your API key and partner ID if you see one.

### Changed

- Each request mints its own token, bound to that request's `user_id` and
  product, instead of reusing one cached token. A 401 is no longer retried with
  a refreshed token.
- A `userId` that is empty or padded with whitespace raises `ValidationError`
  before any request is sent.
- `biometric->authenticate` takes `selfieImage` and `livenessImages` as required
  arguments.

### Added

- `biometricKyc->verify(useEnrolledImage: true, userId: ...)` verifies against
  the user's enrolled selfie; the grant is bound into the job's token and the
  selfie and liveness images may be omitted.

### Removed

- `useEnrolledImage` from `biometric->authenticate`; selfie and liveness images
  are now always required.

## [12.1.0] - 2026-10-01

### Added

- Residency document verification: `documents->verifyResidency()` submits a
  passport and its visa to `/v3/residency_document_verification`.

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

[Unreleased]: https://github.com/smileidentity/smileid-sdk-php/compare/v13.0.0...HEAD
[13.0.0]: https://github.com/smileidentity/smileid-sdk-php/compare/v12.1.0...v13.0.0
[12.1.0]: https://github.com/smileidentity/smileid-sdk-php/compare/v12.0.0...v12.1.0
[12.0.0]: https://github.com/smileidentity/smileid-sdk-php/releases/tag/v12.0.0
