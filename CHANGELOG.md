# Changelog

All notable changes to this project are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Changed

- Base URLs must now be absolute https URLs with no query or fragment, and
  callback URLs (default and per-request) must be https. Insecure values raise
  `ValidationError` before any request is made.
- A 2xx response whose body is not a JSON object now raises the new
  `UnexpectedResponseError` instead of returning empty data.
- `job_id` and `user_id` path parameters are URL-encoded as single path
  segments.
- Multipart filenames and content types are sanitized against header
  injection.
- Removed the client-side `comparison_image_type` enum check: the server owns
  that validation.

- Renamed the Composer package from `smile-identity/core` to `smileid/smileid`.
  The `SmileIdentity\` namespace is unchanged.
- Set the SDK version to 12.0.0, aligning the server SDKs with the V12 mobile
  SDKs.

### Added

- Initial implementation of the Smile ID V3 server-side SDK: all 14 public
  operations, internal JWT auth with caching and refresh-on-401, always-on
  telemetry headers, typed
  error hierarchy, idempotent-only retries, and a `waitUntilComplete` polling
  helper.
