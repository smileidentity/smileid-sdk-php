# Changelog

All notable changes to this project are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Added

- `phone->verify` for `POST /v3/phone`, with the `phone_number_verification`
  token product (spec v3.1215.0).
- `address->verify` for `POST /v3/address`, with the `address_verification`
  token product.
- `smileLinks->create`, `smileLinks->list`, `smileLinks->get` and
  `smileLinks->update` for `POST /v3/smile_links`, `GET /v3/smile_links`,
  `GET /v3/smile_links/{refId}` and `PUT /v3/smile_links/{refId}`, returning
  `SmileLinkCreated`, `SmileLinkListResponse` and `SmileLink` models. Their
  tokens carry no product claim.
- `BasicKycResult` and `OneTimeAmlResult` now carry `userId`,
  `responseSignature` and `responseTimestamp`, read from the `User-ID`,
  `Response-Signature` and `Response-Timestamp` headers of the sync responses.

## [13.2.0] - 2026-11-24

### Added

- `basicKyc->verify` for `POST /v3/basic_kyc`, and `basicKyc->verifySync` for
  `POST /v3/basic_kyc/sync`, which returns a `BasicKycResult` in the response.
  Both mint their token with the `basic_kyc` product claim (spec v3.1215.0).
- `SmileIDError::$jobId`, the job ID from the `SmileID-Job-ID` response header,
  or null when the response has none. It is set on every error raised from an
  HTTP response. After a 408 or 424 from `basicKyc->verifySync`, or a 500 that
  has a job ID, read the outcome with `verifications->retrieve($e->jobId)`
  instead of resubmitting.
- `BasicKycResult::$jobId` and `OneTimeAmlResult::$jobId`, the job ID from the
  `SmileID-Job-ID` header of the `basicKyc->verifySync` and `aml->oneTimeSync`
  responses.
- `enhancedKyc->verifySync` for `POST /v3/enhanced_kyc/sync`, which takes the
  same arguments as `enhancedKyc->verify` and returns an `EnhancedKycResult`:
  the callback payload, with typed `status`, `message`, `reason`, `jobId`,
  `userId` and `kycReceipt`, and the whole body in `data`. `jobId` comes from
  the `SmileID-Job-ID` header, or the body's `job_id` when the header is
  absent, and `userId` from the `User-ID` header, or the body's `user_id`.
  `responseSignature` and `responseTimestamp` carry the `Response-Signature`
  and `Response-Timestamp` headers for your own webhook signature check. It is never retried. After a 408 or 424, or a 500 that has a job
  ID, read the outcome with `verifications->retrieve($e->jobId)` instead of
  resubmitting. Also adds `Operations::enhancedKycSync` and
  `Helpers\ResponseHeader::get`.
- `Transport::sendWithHeaders`, `Operations::basicKycSync`,
  `Operations::oneTimeAmlSync` and `Helpers\JobId::fromHeaders`, which return
  or read the response headers.

## [13.1.0] - 2026-11-19

### Added

- `aml->oneTime` for `POST /v3/aml`, and `aml->oneTimeSync` for `POST /v3/aml/sync`,
  which returns the screening result in the response and accepts an
  `idempotencyKey`.
- `aml->monitoring` for `POST /v3/aml/monitoring`, and `aml->monitoringNews` for
  `GET /v3/aml/monitoring/{job_id}/news/{reference}`.

### Changed

- The AML operations now mint their token with the `product` claim the spec
  names: `one_time_aml` for One-Time AML and its sync variant, and `aml` for AML
  Monitoring and its news lookup.
- The token `product` claim for each operation now comes from the spec's
  `x-smile-token-product` extension, set on the operation's request, instead of
  a table of paths kept in the transport. Claims are unchanged (spec v3.1188.0).

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

[Unreleased]: https://github.com/smileidentity/smileid-sdk-php/compare/v13.2.0...HEAD
[13.2.0]: https://github.com/smileidentity/smileid-sdk-php/compare/v13.1.0...v13.2.0
[13.1.0]: https://github.com/smileidentity/smileid-sdk-php/compare/v13.0.0...v13.1.0
[13.0.0]: https://github.com/smileidentity/smileid-sdk-php/compare/v12.1.0...v13.0.0
[12.1.0]: https://github.com/smileidentity/smileid-sdk-php/compare/v12.0.0...v12.1.0
[12.0.0]: https://github.com/smileidentity/smileid-sdk-php/releases/tag/v12.0.0
